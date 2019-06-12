<?php
namespace ierusalim\FileRecords;

/**
 * This class contains FileRecords
 *
 * Designed to work with fixed-size file records
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
 * @copyright  2018, Ierusalim.com
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

    public $start_base = 0;

    public $source_url = false;
    public $remote_size = false;

    /**
     * Constructor, for example:
     *
     * $fr = new FileRecords("path/file.dat", 64);
     *
     * @param string $file_name
     * @param integer $rec_size
     * @throws Exception
     */
    public function __construct($file_name, $rec_size, $source_url = false)
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
        $this->source_url = $source_url;
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
            if ($this->source_url) {
                $size = $this->remote_size;
                if (($size === false) || $recount) {
                    $size = $this->getHttpSize($url);
                }
            } else {
                $size = $this->file_size = @filesize($this->file_name);
                if (!$size) {
                    $this->file_size = 0;
                }
            }
            if (!$size) {
                return 0;
            }
            $this->rec_cnt = ($size - $this->start_base) / $this->rec_size;
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
    public function readRecord($rec_num, $rec_cnt = 1)
    {
        if (!$this->f && !$this->source_url) {
            if (! @$this->fopen('r', false)) {
                throw new \Exception("Can't open file");
            }
        }
        if (($rec_num >= $this->rec_cnt) || ($rec_num<0)) {
            return false;
        }
        $from_byte = $this->rec_size * $rec_num + $this->start_base;
        $len = $this->rec_size * $rec_cnt;
        if ($this->source_url) {
            $far = $this->getPart($this->source_url, $from_byte, $len);
            $data = isset($far['data']) ? $far['data'] : false;
        } else {
            if (\fseek($this->f, $from_byte)) {
                return false;
            }
            $data = \fread($this->f, $len);
        }
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
     * @param string|array $data Record data
     * @return integer
     * @throws Exception
     */
    public function appendRecord($data)
    {
        if (is_string($data)) {
            $src_arr = [$data];
        } elseif(!is_array($data)) {
            return "ERROR: Unsupported source data type";
        } else {
            $src_arr = $data;
        }
        $wr_cnt = count($src_arr);
        $size = 0;
        foreach($src_arr as $data) {
            $c_size = strlen($data);
            if ($c_size != $this->rec_size) {
                throw new \Exception("Different record size: $c_size (need {$this->rec_size})");
            }
            $size += $c_size;
        }
        $data = implode($src_arr);

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
            $this->rec_cnt = $rec_nmb + $wr_cnt;
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

    /*****************************
     * Pack/Unpack bytes-numbers *
     *****************************/

    /**
     * Pack integer $d to $n bytes string
     *
     * $n in [1..8]
     *
     * Big endian
     *
     * @param int $d
     * @param int $n
     * @return string|false
     */
    public static function packN($d, $n) {
        if ($d < 0) {
            return false;
        }
        switch ($n) {
            case 1:
                if ($d > 255) {
                    return false;
                }
                $r = chr($d);
                break;
            case 2:
                if ($d > 65535) {
                    return false;
                }
                $r = pack('n', $d);
                break;
            case 4:
                if ($d > 4294967295) {
                    return false;
                }
                $r = pack('N', $d);
                break;
            case 3:
            case 5:
            case 6:
            case 7:
            case 8:
                $s = pack('J', $d);
                $r = substr($s, -$n);
                if ($n < 8) {
                    $s = substr($s, 0, 8-$n);
                    $s = str_pad($s, 8, chr(0));
                    $s =unpack('J', $s)[1];
                    if ($s) {
                        return false;
                    }
                }
                break;
            default:
                return false;
        }
        return $r;
    }

    /**
     * Unpack string $d to integer
     *
     * Big endian
     *
     * @param string $d
     * @return integer
     */
    public static function unpackN($d)
    {
        $base = 1;
        $sum = 0;
        while (strlen($d)) {
            $c = substr($d, -1);
            $sum += ord($c) * $base;
            $base *= 256;
            $d = substr($d, 0, -1);
        }
        return $sum;
    }

    /********************
     * Access functions *
     ********************/

    /**
     * Try to read first bytes from specified source
     *
     * @param string|resource $src
     * @param int $start_base
     * @param int $max_first_len
     * @param int $min_first_len
     * @return array|false
     */
    public function readFirstBytes($src, $start_base = 0, $max_first_len = 275 * 4, $min_first_len = 4)
    {
          $far = $this->getPart($src, $start_base, $max_first_len);

          if (!isset($far['data']) || ($far['data'] === false)) {
              return false;
          }
          $data = $far['data'];
          $len = strlen($data);

          if ($len < $min_first_len) {
              return false;
          }

          if (isset($far['total_size']) && ($far['total_size'] >= 0)) {
              // size for http/https object (from http-headers)
              $total_size = $far['total_size'];
          } elseif (!$start_base) {
              // it readed data size less of requested size, it is total-size
              $total_size = ($len < $max_first_len) ? $len : -1;
          }
          // result: known $total_size >=0 or -1 if unknown

          $remote = isset($far['remote']) ? $far['remote'] : false;

          return compact('data', 'len', 'total_size', 'remote');
    }

    /**
     * Get part of specified file $src from $from_byte
     *
     * In:
     * - Supported src variants:
     *   1. (string) file name (path to local file)
     *   2. (string) URL http or https -> getHttpRange
     *   3. (resource) opened file or stream resource
     *
     * Out:
     * - Error:   false
     * - Success: array [data, total_size, is_url]
     *
     *   - [data] may contain false (if error)
     *   - [total_size] will contain -1 (if size unknown)
     *   - optional key: [headers] - for http/https answers
     *
     * @param string|resource $src
     * @param type $from_byte Start byte for part reading
     * @param type $len Length of part
     * @return array|false
     */
    public function getPart($src, $from_byte, $len)
    {
        $total_size = -1;
        $remote = false;

        if (is_resource($src)) {
            if (fseek($src, $from_byte)) {
                return false;
            }
            $data = \fread($src, $len);
            $remote = !stream_is_local($src);
        }

        if (is_string($src)) {
            $left6 = strtolower(substr($src,0, 6));
            if ($left6 == 'https:' || $left6 == 'http:/') {
                return $this->getHttpRange($src, $from_byte, $len);
            }
            // try read file if exists
            if (is_file($src)) {
                $data = \file_get_contents($src, false, NULL, $from_byte, $len);
            } else {
                $data = false;
            }
        }
        return compact('data', 'total_size', 'remote');
    }

    /**
     * Reads part of the file over http/https
     *
     * This implementation differs in that it sends a http-Range header.
     * Standard implementation php-http-wrapper not support seeking.
     *
     * @param string $url Source URL
     * @param int $from_byte Start byte for part reading
     * @param int $len Length of part
     * @return array have keys [data, total_size, headers]
     */
    public function getHttpRange($url, $from_byte, $len)
    {
        $to_byte = $from_byte + $len - 1;
        $data = @\file_get_contents ($url, false, stream_context_create([
            'http' => [
                'method'=>"GET",
                'header'=>"Range: bytes={$from_byte}-{$to_byte}\r\n"
                ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'verify_depth' => 0,
            ],
        ])) ;

        $total_size = -1;

        $remote = $http_response_header;

        if (($data !== false) && is_array($remote)) {
            foreach($remote as $h) {
                $i = stripos($h, 'ange: bytes ');
                if ($i) {
                    $i=strrpos($h, '/');
                    if ($i) {
                        $total_size = (int)substr($h, $i+1);
                        $this->remote_size = $total_size;
                    }
                }
            }
        }
        return compact('data', 'total_size', 'remote');
    }

    public function getHttpSize($url)
    {
        $data = @\file_get_contents ($url, false, stream_context_create([
            'http' => [
                'method'=>"HEAD",
                ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'verify_depth' => 0,
            ],
        ])) ;

        $remote = $http_response_header;
        $content_length = false;
        if (($data !== false) && is_array($remote)) {
            $content_length = 0;
            foreach($remote as $h) {
                $i = stripos($h, 'nt-Length:');
                if ($i) {
                    $content_length = (int)substr($h, $i+10);
                    $this->remote_size = $content_length;
                }
            }
        }
        return $content_length;
    }
}