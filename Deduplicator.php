<?php

/**
 * Dedulicate class : Considers the following
 * 
 * Take a variable number of identically structured json records and de-duplicate the set.
 * An example file of records is given in the accompanying 'leads.json'. 
 *
 * Output should be same format, with dups reconciled 
 * according to the following rules:
 *
 * 1. The data from the newest date should be preferred.
 * 2. Duplicate IDs count as dups.
 *    Duplicate emails count as dups.
 *    Both must be unique in our dataset.
 *    Duplicate values elsewhere do not count as dups.
 * 3. If the dates are identical the data from the record provided last in the list should be preferred.
 * 
 * The application should also provide a log of changes including some representation of the source record, 
 * the output record and the individual field changes (value from and value to) for each field.
 * 
 * NOTE: Implement as CLI Script
 * 
 * Usage : Class is called via run.php that handles input params for file.
 *         php run.php --input=leads.json --output=output.json --log=changes.log
 *
 * @author   Nadeem Ahmad <naddy.ahmed@gmail.com>
 * @version  1.1.0
 * @see      https://github.com/highwaysniper001/deduplicate-leads
 * @since    2025-05-30
 * @category Migration
 * 
 */

 /**
  * Class Deduplicator
  *
  * A class that deduplicates JSON records based on id and email fields,
  * preferring newer entryDate records. 
  */
class Deduplicator
{
    /** 
     * @var array Array of input records from json file
     */
    private $records;

    /** 
     * @var array The final deduplicated records
     */
    private $finalRecords;

    /** 
     * @var array Mapped latest record IDs
     */
    private $idMap;

    /** 
     * @var array Mapped latest emails
     */
    private $emailMap;

    /**
     * @var string Logs file path
     */
    private $logFile;

    /**
     * constructor
     *
     * @param string $inputFile Path to the input JSON file.
     * @param string $logFile   Path to the log file (default 'changes.log').
     */
    public function __construct(string $inputFile, string $logFile = 'changes.log')
    {
        $this->records = $this->loadJson($inputFile);
        $this->finalRecords = [];
        $this->idMap = [];
        $this->emailMap = [];
        $this->logFile = $logFile;
        $this->ensureLogFileExists();
        file_put_contents($this->logFile, ""); // clear log
    }

    /**
     * Logs
     *
     * @param array $sourceRecord Original records.
     * @param array $outputRecord Processed records.
     * @param array $changes      Changes.
     * 
     * @return void
     */
    private function logChange(array $sourceRecord, array $outputRecord, array $changes): void
    {
        $logEntry = "Original Record:" . PHP_EOL 
                    . json_encode($sourceRecord) . PHP_EOL;
        $logEntry .= "Processed Record:" . PHP_EOL 
                    . json_encode($outputRecord) . PHP_EOL;
        $logEntry .= "Changes:" . PHP_EOL;
        foreach ($changes as $field => $values) {
            $logEntry .= "  {$field}: '{$values['from']}' → '{$values['to']}'" . PHP_EOL;
        }
        $logEntry .= str_repeat("-", 200) . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Deduplicates records based on id and email, preferring the later
     * Note: If dates are equal, the latest in the list replaces the older one.
     *
     * @return void
     */
    public function deduplicate(): void
    {
        foreach ($this->records as $record) {
            // Validate record
            if (
                empty($record['_id']) ||
                empty($record['email']) ||
                empty($record['entryDate'])
            ) {
                $this->logSkipped($record, 'Missing required fields (_id, email, or entryDate)');
                continue;
            }

            $recordId = $record['_id'];
            $email = $record['email'];
            $date = strtotime($record['entryDate']);
            // CHeck strictly
            //$date = DateTime::createFromFormat('Y-m-d H:i:s', $record['entryDate']);

            if (!$date) {
                $this->logSkipped($record, 'Invalid entryDate format');
                continue;
            }

            $existingById = $this->idMap[$recordId] ?? null;
            $existingByEmail = $this->emailMap[$email] ?? null;

            $toCompare = null;

            if ($existingById) {
                $toCompare = $existingById;
            } elseif ($existingByEmail) {
                $toCompare = $existingByEmail;
            }

            if ($toCompare) {
                $existingDate = strtotime($toCompare['entryDate']);
                $replace = false;

                if ($date > $existingDate || $date === $existingDate) {
                    $replace = true;
                }

                if ($replace) {
                    $changes = [];

                    foreach ($record as $key => $value) {
                        if (!array_key_exists($key, $toCompare) || $toCompare[$key] !== $value) {
                            $changes[$key] = [
                                'from' => $toCompare[$key] ?? 'N/A',
                                'to'   => $value
                            ];
                        }
                    }
                    // Logs
                    $this->logChange($toCompare, $record, $changes);
                    // Prepare record
                    $this->finalRecords[$recordId] = $record;
                    $this->idMap[$recordId] = $record;
                    $this->emailMap[$email] = $record;
                }
            } else {
                // Prepare record
                $this->finalRecords[$recordId] = $record;
                $this->idMap[$recordId] = $record;
                $this->emailMap[$email] = $record;
            }
        }
    }

    /**
     * Loads records from file
     *
     * @param string $filePath File path.
     *
     * @throws RuntimeException If the file does not exist or contains invalid JSON.
     *
     * @return array JSON decoded records as array
     */
    private function loadJson(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(
                "Input file '{$filePath}' not found."
            );
        }

        $json = file_get_contents($filePath);
        if (trim($json) === '') {
            $data = [];
        } else {
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    "Invalid JSON format in '{$filePath}'. Error: " . json_last_error_msg()
                );
            }
        }
        if (isset($data['leads']) && is_array($data['leads'])) {
            $result = $data['leads'];
        } else {
            $result = $data;
        }
        return $result;
    }

    /**
     * Save records
     *
     * @param array  $data     Array of records to save.
     * @param string $filePath File path.
     *
     * @throws RuntimeException If unable to write to file
     *
     * @return void
     */
    private function saveJson(array $data, string $filePath): void
    {
         $json = json_encode(
            [
                'leads' => array_values($data)
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        if (file_put_contents($filePath, $json) === false) {
            throw new RuntimeException(
                "Failed to write to output file '{$filePath}'. Check permissions."
            );
        }
    }

    /**
     * Save records in file
     *
     * @param  string $outputFile File path.
     * @return void
     */
    public function saveOutput(string $outputFile): void
    {
        $this->saveJson($this->finalRecords, $outputFile);
    }

    /**
     * Create if log file doesn't exist, 
     * throw an exception if can't create
     *
     * @throws RuntimeException If the log file cannot be created.
     *
     * @return void
     */
    private function ensureLogFileExists(): void
    {
        if (empty($this->logFile)) {
            throw new RuntimeException("Log file path is empty.");
        }
        if (!file_exists($this->logFile)) {
            if (file_put_contents($this->logFile, "") === false) {
                throw new RuntimeException("Failed to create log file at '{$this->logFile}'. Check permissions.");
            }
        }
    }

    /**
     * Logs a skipped record with a specified reason.
     *
     * @param array  $record Skipped record
     * @param string $reason Details why the record was skipped
     *
     * @return void
     */
    private function logSkipped(array $record, string $reason): void
    {
        $logEntry = "Skipped Record (Reason: $reason):" . PHP_EOL
                . json_encode($record) . PHP_EOL
                . str_repeat("-", 200) . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
}

?>
