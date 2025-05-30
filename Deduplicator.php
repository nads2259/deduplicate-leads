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
 * @version  1.0.0
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

        // Clear log file
        file_put_contents($this->logFile, "");
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
            $recordId = $record['_id'];
            $email = $record['email'];
            $date = strtotime($record['entryDate']);

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

                if ($date > $existingDate) {
                    $replace = true;
                } elseif ($date === $existingDate) {
                    // If dates are equal, take later record
                    $replace = true;
                }

                if ($replace) {
                    $changes = [];

                    foreach ($record as $key => $value) {
                        if ($toCompare[$key] !== $value) {
                            $changes[$key] = [
                                'from' => $toCompare[$key],
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
     * @return array JSON decoded records.
     */
    private function loadJson(string $filePath): array
    {
        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        if (isset($data['leads']) && is_array($data['leads'])) {
            return $data['leads'];
        }
        return $data;
    }

    /**
     * Save records
     *
     * @param array  $data     Array of records to save.
     * @param string $filePath File path.
     * 
     * @return void
     */
    private function saveJson(array $data, string $filePath): void
    {
        $json = json_encode(['leads' => array_values($data)], JSON_PRETTY_PRINT);
        file_put_contents($filePath, $json);
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
}

?>
