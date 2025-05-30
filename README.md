# PHP Lead Deduplication CLI (OOP)

PHP CLI Script to deduplicate lead JSON records on below considerations.

## Deduplicator class logic considers the following

- Only duplicate `id` or `email` values are counted as duplicates.
- Prefers records with the newest `entryDate`. Note: If identical dates, the later record is preferred.
- Logs all field changes between duplicates.

## Usage

If using the base file present in the directory
```bash
php run.php --input=leads.json --output=output.json --log=changes.log
```

## CLI Sample Output
```bash
Deduplication complete.
Results saved to output.json.
Log written to changes.log.
```

### Logs format
```bash
Original Record:
{"id":"1","email":"***@gmail.com","firstName":"***","lastName":"***","address":"***","entryDate":"2024-05-01T10:00:00+02:00"}
Processed Record:
{"id":"1","email":"***@gmail.com","firstName":"***","lastName":"***","address":"****","entryDate":"2024-06-01T09:00:00+02:00"}
Changes:
  address: '***' → '****'
  entryDate: '2024-05-01T10:00:00+02:00' → '2024-06-01T09:00:00+02:00'
```
