<?php

use memberpress\courses\lib\Utils;

class MeprBatchMigrator {
  /**
   * How many seconds the migrator should run for before sending a response back to the browser.
   *
   * It is set below 30 seconds, as some servers have this set as the PHP max execution time.
   *
   * @var int
   */
  const TIMEOUT = 25;

  /**
   * The start time.
   *
   * @var float
   */
  protected $start;

  /**
   * The number of items processed since the start.
   *
   * @var int
   */
  protected $processed = 0;

  /**
   * The maximum number of items to retrieve in each batch.
   *
   * @var int
   */
  protected $limit = 1;

  /**
   * The current offset value for batching.
   *
   * @var int
   */
  protected $offset = 0;

  /**
   * The items in the current batch.
   *
   * @var array
   */
  protected $items = [];

  /**
   * Function to get the next batch of items.
   *
   * @var Closure
   */
  protected $fetch_batch;

  /**
   * True if we are processing the first batch.
   *
   * @var bool
   */
  protected $first_batch = true;

  /**
   * @param Closure $fetch_batch Function to get the next batch of items.
   * @param int     $limit       The starting limit.
   * @param int     $offset      The starting offset.
   */
  public function __construct(Closure $fetch_batch, int $limit, int $offset) {
    $this->fetch_batch = $fetch_batch;
    $this->limit = $limit;
    $this->offset = $offset;
    $this->start = microtime(true);
  }

  /**
   * Retrieves the maximum number of items to fetch in each batch.
   *
   * @return int
   */
  public function get_limit(): int {
    return $this->limit;
  }

  /**
   * Retrieves the offset value for batching.
   *
   * @return int
   */
  public function get_offset(): int {
    return $this->offset;
  }

  /**
   * Is there at least one item in the current batch?
   *
   * @return bool
   */
  public function has_items(): bool {
    return !empty($this->items);
  }

  /**
   * Get the current batch of items.
   *
   * @return array
   */
  public function get_items(): array {
    return $this->items;
  }

  /**
   * Fetch the next batch of items and determines if we can we proceed with processing the next batch.
   *
   * We can proceed if this is the first batch, or the projected time to complete the next batch will not take us over
   * the timeout.
   *
   * @return bool
   */
  public function next_batch(): bool {
    if($this->first_batch) {
      $this->first_batch = false;
    }
    else {
      MeprMigrator::free_memory();

      // Move to the offset to the start of the next batch
      $this->offset += $this->limit;

      // Update the total count of items processed in this request
      $this->processed += count($this->items);

      // Calculate the number of items to process in the next batch
      if($this->processed > 0) {
        $elapsed_time = microtime(true) - $this->start;
        $time_per_item = $elapsed_time / $this->processed;

        if($time_per_item > 0) {
          $this->limit = Utils::clamp(intval(4 / $time_per_item), 1, 500);
        }
      }
    }

    $this->items = ($this->fetch_batch)($this->limit, $this->offset);

    if(empty($this->items)) {
      return false;
    }

    if($this->processed > 0) {
      $elapsed_time = microtime(true) - $this->start;
      $time_per_item = $elapsed_time / $this->processed;
      $projected_batch_time = count($this->items) * $time_per_item;

      return ($elapsed_time + $projected_batch_time) < self::TIMEOUT;
    }

    return true;
  }
}
