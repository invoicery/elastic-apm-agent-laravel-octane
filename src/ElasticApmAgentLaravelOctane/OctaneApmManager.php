<?php

namespace Cego\ElasticApmAgentLaravelOctane;

use BadMethodCallException;
use Elastic\Apm\ElasticApm;
use InvalidArgumentException;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

class OctaneApmManager
{
    /**
     * The main outer transaction wrapping all child spans.
     *
     * @var TransactionInterface
     */
    private TransactionInterface $transaction;

    /**
     * Holds all stored spans indexed by their name
     *
     * @var array<string, SpanInterface>
     */
    private array $spans = [];

    /**
     * Begins a new transaction or returns the current transaction
     *
     * @param string $name
     * @param string $type
     *
     * @return TransactionInterface|null
     */
    public function beginTransaction(string $name, string $type): ?TransactionInterface
    {
        // If there is a hanging transaction, then discard it.
        if ( ! isset($this->transaction) && ! ElasticApm::getCurrentTransaction()->hasEnded()) {
            ElasticApm::getCurrentTransaction()->discard();
        }

        return $this->transaction ??= ElasticApm::beginCurrentTransaction($name, $type);
    }

    /**
     * Begins a new span
     *
     * @param string $name
     * @param string $type
     *
     * @return SpanInterface|null
     */
    public function beginAndStoreSpan(string $name, string $type): ?SpanInterface
    {
        if ($this->hasNoTransactionInstance()) {
            throw new BadMethodCallException('Cannot start span without first starting a transaction');
        }

        if (isset($this->spans[$name])) {
            throw new InvalidArgumentException('Nested stored spans with the same name is not supported');
        }

        return $this->spans[$name] = $this->transaction->beginChildSpan($name, $type);
    }

    /**
     * Ends a stored span
     *
     * @param string $name
     *
     * @return void
     */
    public function endStoredSpan(string $name): void
    {
        if ( ! isset($this->spans[$name])) {
            throw new InvalidArgumentException('No stored span with name [%s] exists');
        }

        $this->spans[$name]->end();
        unset($this->spans[$name]);
    }

    /**
     * Returns the current transaction
     *
     * @return TransactionInterface|null
     */
    public function getTransaction(): ?TransactionInterface
    {
        return $this->transaction ?? null;
    }

    /**
     * Returns true if there exists a transaction instance within the manager
     *
     * @return bool
     */
    public function hasNoTransactionInstance(): bool
    {
        return ! isset($this->transaction);
    }

    /**
     * Ends the transaction
     *
     * @return void
     */
    public function endTransaction(): void
    {
        if ($this->hasNoTransactionInstance()) {
            throw new BadMethodCallException('Cannot start transaction before it has been started');
        }

        if ( ! $this->transaction->hasEnded()) {
            $this->transaction->end();
        }
    }
}
