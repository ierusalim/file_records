<?php
namespace ierusalim\FileRecords;

/**
 * This class contains FileRecords
 *
 * Designed to work with fixed-size records
 *
 * Functions:
 * new FileRecords($fileName,$record_size) - init for work with specified file
 * ->recordsCount() - return records count (counted as file_size / record_size)
 * ->appendRecord($data) - append fixed-size string to end of file, return record number
 * ->readRecord($number) - read fixed-size string from file by record-number
 * ->reWriteRecord($number,$data) - re-Write new fixed-string over old by record-number
 *
 * PHP Version >= 5.6
 *
 * @package    ierusalim\FileRecords
 * @author     Alexander Jer <alex@ierusalim.com>
 * @copyright  2018, Ierusalim
 * @license    https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */
class FileRecords
{
    public $file_name;
    public $rec_size;
    public $rec_cnt;
    public $file_size;
    public $file_size_limit = false;
    public $f = false; // fopen-descriptor (false if not open)
    public $f_mode;

    /**
     * Constructor, for example:
     *
     * $fr = new \ierusalim\FileRecords("path/file.dat", 64);
     *
     * @param string $file_name
     * @param integer $rec_size
     * @throws Exception
     */
    public function __construct($file_name, $rec_size)
    {
        if (
            (!is_numeric($rec_size))
            ||
            ($rec_size != (int)$rec_size)
            ||
            ($rec_size < 1)
        ) {
            throw new \Exception("Record size must be integer and grater than 0");
        }
        $this->file_name = $file_name;
        $this->rec_size = (int)$rec_size;
    }

    /**
     * Return count of records in file
     * Calculate from file-size and record-size
     * Return 0 if no records (or no file)
     *
     * @return integer
     */
    public function recordsCount($recount = false)
    {
        if (!$this->f || $recount) {
            $this->file_size = @filesize($this->file_name);
            if (!$this->file_size) {
                $this->file_size = 0;
            }
            $this->rec_cnt = $this->file_size / $this->rec_size;
        }
        return $this->rec_cnt;
    }

    /**
     * Read Record from file by specified number
     *
     * @param int $rec_num Record number
     * @return string|false Data string (or false if error)
     * @throws Exception
     */
    public function readRecord($rec_num)
    {
        if (!$this->f) {
            if (! @$this->fopen('r', false)) {
                throw new \Exception("Can't open file");
            }
        }
        if (($rec_num >= $this->rec_cnt) || ($rec_num<0)) {
            return false;
        }
        if (\fseek($this->f, $this->rec_size * $rec_num)) {
            return false;
        }
        $data = \fread($this->f, $this->rec_size);
        return $data;
    }

    /**
     * Close current file
     */
    public function fclose() {
        if ($this->f) {
            @\fclose($this->f);
        }
        $this->f = false;
    }

    public function __destruct()
    {
        $this->fclose();
    }
    /**
     * Open file for Read, Append or reWrite mode.
     *
     * @param int $new_f_mode r = Readonly, a = Append, w = reWrite
     * @param boolean $re_open true for close opened file and open again
     */
    public function fopen($new_f_mode, $re_open = false)
    {
        switch ($new_f_mode) {
        case 'r':
        case 'rb':
            $f_mode = 'rb';
            break;
        case 'a':
        case 'ab+':
            $f_mode = 'ab+';
            break;
        case 'w':
        case 'rb+':
            $f_mode = 'rb+';
            break;
        default:
            throw new \Exception("Unrecognized mode: $new_f_mode \nSupported: r, w, a");
        }

        // if file already open
        if ($this->f) {
            if (
                !$re_open
             && (($this->f_mode === $new_f_mode) || ($new_f_mode === 'rb'))
            ) {
                return $this->f;
            }
            $this->fclose();
        }

        $rec_cnt = $this->recordsCount();

        if (!$rec_cnt && ($f_mode === 'rb+')) {
            // Can't open file to reWrite if no records
            return false;
        }

        $this->f = @fopen($this->file_name, $f_mode);
        if (!$this->f) {
            return false;
        }
        $this->f_mode = $f_mode;
        return $this->f;
    }

    /**
     * Append new record to the end of file
     *
     * Returns: integer record number or string error description
     *
     * @param string $data Record data
     * @return integer
     * @throws Exception
     */
    public function appendRecord($data)
    {
        $size = strlen($data);
        if ($size != $this->rec_size) {
            throw new \Exception("Different record size: $size (need {$this->rec_size})");
        }
        $f = $this->fopen('ab+', false);
        if (!$f) {
            return "ERROR: Can't open file for append";
        }
        if (@\flock($f, \LOCK_EX)) {
            \clearstatcache(true, $this->file_name);
            $rec_nmb = $this->recordsCount(true);
            if ($this->file_size_limit && (($this->file_size + $size) > $this->file_size_limit)) {
                \flock($f, \LOCK_UN);
                return "FILE_SIZE_LIMIT is reached";
            }
            $bcnt = @\fwrite($f, $data, $size);
            \flock($f, \LOCK_UN);
            \clearstatcache(true, $this->file_name);
            if ($bcnt !== $size) {
                return "ERROR: Can't write record #{$rec_nmb} to file\n" . error_get_last()['message'];
            }
            $this->rec_cnt = $rec_nmb + 1;
            $this->file_size += $size;

        } else {
            return "ERROR: Can't lock file";
        }
        return $rec_nmb;
    }

    /**
     * ReWrite existing record in file by record number
     *
     * Returns: integer record number or string error description
     *
     * @param integer $rec_num
     * @param string $data
     * @return integer|string
     * @throws Exception
     */
    public function reWriteRecord($rec_num, $data, $ignore = false)
    {
        $size = strlen($data);
        if (!$ignore && ($size != $this->rec_size)) {
            throw new \Exception("Different record size: $size (need {$this->rec_size})");
        }
        $rec_max = $this->recordsCount() - 1;
        if (($rec_num < 0) || ($rec_num > $rec_max)) {
            return "Record #$rec_num out of records range [0 - $rec_max)";
        }

        $f = $this->fopen('rb+', false);
        if (!$f) {
            return "ERROR: Can't open file for re-write";
        }

        $seek_pos = $rec_num * $this->rec_size;

        if (@fseek($f, $seek_pos)) {
            return "ERROR: Can't fseek record #{$rec_num}\n" . error_get_last()['message'];
        }

        $bcnt = @fwrite($f, $data, $size);
        if ($bcnt !== $size) {
            return "ERROR: Can't write record #{$rec_num}\n". error_get_last()['message'];
        }
        return $rec_num;
    }
}