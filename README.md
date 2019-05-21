# FileRecords

## Functions:

- ->new FileRecords($file_name, $record_size)
- ->appendRecord($data)
- ->readRecord($record_number)
- ->reWriteRecord($record_number, $new_data)

## Example:

```php
<?php
    namespace ierusalim\ClickHouse;

    require "vendor/autoload.php";

    $fr = new FileRecords("test.dat",8);

    $fr->appendRecord("01234567");
    $fr->appendRecord("Abc defg");

    echo $fr->recordsCount();
    // 2

    echo $fr->readRecord(1);
    // Abc defg
    echo $fr->readRecord(0);
    // 01234567

    $fr->reWriteRecord(1,"lala lal");
    echo $fr->readRecord(1);
    // lala lal

```