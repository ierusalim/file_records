<?php
namespace ierusalim\FileRecords;

/**
 * This class contains functions for DynamicSize-Records System (files-based)
 *
 * Functions:
 * ->appendRecord($data) -- return record number
 * ->readRecord($record_number)
 *
 * .dat data file:
 * contains records (any binary data)
 *
 * .idx index file:
 * contains fixed-size records with offset and length about each records in .dat
 *
 * .drs header file contains serialized array with those parameters:
 * [records_per_file]
 * [bytes_for_offset]
 * [bytes_for_length]
 *
 * Example:
   require "vendor/autoload.php";

   $dr = new \ierusalim\FileRecords\DRS('test', [
        'records_per_file' => 100000,
        'bytes_for_offset' => 3,
        'bytes_for_length' => 2
    ]);

   $n1 = $dr->appendRecord("lalala!");
   $n2 = $dr->appendRecord("bla-bla-bla!");

   echo $dr->readRecord($n2);
   echo $dr->readRecord($n1);
 *
 *
 * PHP Version >= 5.6
 *
 * @package    ierusalim\FileRecords
 * @author     Alexander Jer <alex@ierusalim.com>
 * @copyright  2018, Ierusalim
 * @license    https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */
define ('HEADER_EXT', '.drs');
define ('INDEX_EXT', '.idx');
define ('DATA_EXT', '.dat');

class DRS
{
    public $base_path;
    public $base_name;
    public $files_cnt = false;
    public $records_cnt = false;
    public $fr_opened = [];
    public $filesNarr = false;

    public $records_per_file; //1-100000000
    public $bytes_for_offset; //2, 3, 4
    public $bytes_for_length; //1, 2, 3, 4

    public $popytkon = 0;

    public function filesArr($recount = false)
    {
        if (($this->filesNarr === false) || $recount) {
            $pattern = $this->base_path . $this->base_name . '-';
            $plen = strlen($pattern);
            $arr = glob($pattern . '*' . INDEX_EXT);
            foreach($arr as $k => $fileN) {
                $arr[$k] = strstr(substr($fileN, $plen), '.', true);
            }
            sort($arr, \SORT_NUMERIC);
            $this->filesNarr = $arr;
        }
        return $this->filesNarr;
    }

    public function recordsCount($recount = false)
    {
        if (($this->records_cnt === false) || $recount) {
            $f_arr = $this->filesArr($recount);
            $c = count($f_arr);
            if ($c) {
                $maxN = $f_arr[$c - 1]; // get Maximum File Number
                $records_in_prev = $maxN * $this->records_per_file;
                $fr = $this->frOpen($maxN);
                $records_in_last = $fr->recordsCount($recount);
                $this->records_cnt = $records_in_prev + $records_in_last;
            } else {
                $this->records_cnt = 0;
            }
        }
        return $this->records_cnt;
    }

    public function __construct($headerFile, $init_array = false)
    {
        $base_path = dirname($headerFile);
        $cwd = \getcwd();
        if ($base_path === '.') {
            $base_path = $cwd;
        } elseif (
            (strlen($base_path) > 1)
        && (
            (substr($base_path, 0, 1) === DIRECTORY_SEPARATOR)
         || (substr($base_path, 1, 1) ===  ':')
           )
        ) {
            $this->base_path = $base_path . DIRECTORY_SEPARATOR;
        } else {
            $this->base_path = $cwd . DIRECTORY_SEPARATOR . $base_path . DIRECTORY_SEPARATOR;
        }

        $base_name = \basename($headerFile);

        $dot = strrpos($base_name, '.');
        $ext = is_integer($dot)? substr($base_name, $dot) : '';

        if ($dot === false) {
            $dot = strlen($base_name);
        } elseif (!empty($ext) && ($ext !== HEADER_EXT)) {
            throw new \Exception("Header file-extention must be empty or " . HEADER_EXT);
        }
        $base_name = substr($base_name, 0, $dot);
        if (false !== ($dot = strrpos($base_name, '-'))) {
            $base_name = substr($base_name, 0, $dot);
        }
        $this->base_name = $base_name;
        if (empty($base_name)) {
            throw new Exception("Header file-name must be not-empty");
        }

        $arr = $this->initHeaderFile($init_array);
        extract($arr);
        $this->records_per_file = $records_per_file;
        $this->bytes_for_offset = $bytes_for_offset;
        $this->bytes_for_length = $bytes_for_length;
    }

    /**
     * Check required parameters in header-array
     *   [records_per_file] 1..100 000 000
     *   [bytes_for_offset] 2-4
     *   [bytes_for_length] 1-4
     * @param array $arr
     * @return false|array
     */
    public function headerArrayValidate($arr)
    {
        if (
            !isset($arr['records_per_file']) ||
            !isset($arr['bytes_for_offset']) ||
            !isset($arr['bytes_for_length'])
            )
        return false;

        $records_per_file = $arr['records_per_file'];
        $bytes_for_offset = $arr['bytes_for_offset'];
        $bytes_for_length = $arr['bytes_for_length'];

        if ($records_per_file < 1 || $records_per_file > 100000000) {
            return false;
        }

        if ($bytes_for_offset < 2 || $bytes_for_offset > 4) {
            return false;
        }

        if ($bytes_for_length <1 || $bytes_for_length >4) {
            return false;
        }
        return \compact(
            'records_per_file',
            'bytes_for_offset',
            'bytes_for_length'
            );
    }

    public function initHeaderFile($init_array = false)
    {
        $headerFile = $this->base_path . $this->base_name . HEADER_EXT;
        if (!\file_exists($headerFile)) {
            if (empty($init_array)) {
                throw new \Exception("Header file '$headerFile' not found");
            } else {
                $arr = $this->headerArrayValidate($init_array);
                if (is_array($arr)) {
                    $data = \serialize($init_array);
                    if (unserialize($data) !== $init_array) {
                        throw new \Exception("Can't serialize init_array");
                    }
                    if (!\file_put_contents($headerFile, $data)) {
                        throw new \Exception("Can't write header file '$headerFile'");
                    }
                    return $arr;
                } else {
                    throw new \Exception("Invalid Init-array for header file '$headerFile'");
                }
            }
        } else {
            $data = \file_get_contents($headerFile, false, null, 0, 1000);
            $arr = \unserialize($data);
            if (is_array($arr)) {
                $arr = $this->headerArrayValidate($arr);
            }
            if (is_array($arr)) {
                if (!empty($init_array) && ($arr !== $init_array)) {
                    throw new Exception("Header file '$headerFile' data not equal with specified init_array");
                }
                return $arr;
            } else {
                throw new \Exception("Header file '$headerFile' data is invalid");
            }
        }
    }

    public function calcIndexPointer($rec_num)
    {
        $file_nmb = floor($rec_num / $this->records_per_file);
        $file_base = $this->base_path . $this->base_name . '-' . $file_nmb;
        $idx_size = $this->bytes_for_offset + $this->bytes_for_length;
        $rec_nmb_in_idx = $rec_num - $file_nmb * $this->records_per_file;
        return compact(
            'file_base',
            'file_nmb',
            'idx_size',
            'rec_nmb_in_idx'
            );
    }

    public function appendRecord($data)
    {
        $rec_nmb = $this->recordsCount(true);
        $po = $this->calcIndexPointer($rec_nmb);
        extract($po); // $file_base, $file_nmb, $idx_size, $rec_nmb_in_idx
        $fr = $this->frOpen($file_nmb);

        $rec_size = strlen($data);

        $file_dat = $file_base . DATA_EXT;
        $fd = fopen($file_dat, 'ab');
        if (!$fd) {
            return "Can't open data file=$file_dat for append record";
        }
        if (@\flock($fd, \LOCK_EX)) {
            \clearstatcache(true, $file_dat);
            $rec_offset = filesize($file_dat);
            $wbcnt = @\fwrite($fd, $data, $rec_size);
            \flock($fd, \LOCK_UN);
        } else {
            fclose($fd);
            return "Can't lock data-file";
        }
        fclose($fd);
        if ($wbcnt !== $rec_size) {
            return "Writed data size=$wbcnt not equal record size=$rec_size rec_nmb=$rec_nmb";
        }

        $idx_dat = $this->packIdx($rec_offset, $rec_size);
        if ($idx_dat === false) {
            return "Can't pack idx-record (header overflow: offset=$rec_offset, len=$rec_size)";
        }
        $idx_num = $fr->appendRecord($idx_dat);
        if (is_string($idx_num) && ($idx_num === 'FILE_SIZE_LIMIT is reached')) {
            // Try again
            if ($this->popytkon++ > 3) return false;
            usleep(50000);
            return $this->appendRecord($data);
        }
        $this->popytkon = 0;
        return $file_nmb * $this->records_per_file + $idx_num;
    }

    public function readRecord($rec_nmb, $try_max = 5, $try_interval = 10000)
    {
        for ($i = 0; $i < $try_max; $i++) {
            $data = $this->_readRecord($rec_nmb);
            if ($data !== false) break;
            \usleep ($try_interval);
        }
        return $data;
    }
    public function _readRecord($rec_nmb)
    {
        $po = $this->calcIndexPointer($rec_nmb);
        extract($po); // $file_base, $file_nmb, $idx_size, $rec_nmb_in_idx
        $fr = $this->frOpen($file_nmb);
        $idx = $fr->readRecord($rec_nmb_in_idx);
        if ($idx === false) {
            return false;
        }
        $idx = $this->unpackIdx($idx);
        if (!isset($idx['offset'])) {
            return false;
        }
        $offset = $idx['offset'];
        $length = $idx['length'];

        if (!$length) {
            return '';
        }
        $file_dat = $file_base . DATA_EXT;
        $fd = fopen($file_dat, 'rb');
        if (!$fd) {
            throw new \Exception("Can't open data file=$file_dat for read");
        }
            if (fseek($fd, $offset)) {
                fclose($fd);
                throw new \Exception("Can't seek to point $offset in file $file_dat");
            }
            for ($i = 0; $i<5; $i++) {
                $data = fread($fd, $length);
                if (strlen($data) == $length) break;
                usleep(10000);
            }
            if (!strlen($data)) $data = false;
        fclose($fd);
        return $data;
    }

    public function frOpen($fr_n)
    {
        if (!isset($this->fr_opened[$fr_n])) {
            $keys = array_keys($this->fr_opened);
            if (count($keys)>9) { // If limit reached Close random
                $random_key = $keys[mt_rand(0,count($keys)-1)];
                unset($this->fr_opened[$random_key]);
            }
            $index_file = $this->base_path . $this->base_name . '-' . $fr_n . INDEX_EXT;
            $rec_size = $this->bytes_for_offset + $this->bytes_for_length;
            $fr = new \ierusalim\FileRecords\FileRecords($index_file, $rec_size);
            $fr->file_size_limit = $rec_size * $this->records_per_file;
            $this->fr_opened[$fr_n] = $fr;
        }
        return $this->fr_opened[$fr_n];
    }

    public function packIdx($r_off, $r_len)
    {
        $d_off = $this->packN($r_off, $this->bytes_for_offset);
        if ($d_off === false) return false;
        $l_off = $this->packN($r_len, $this->bytes_for_length);
        if ($l_off === false) return false;
        return $d_off . $l_off;
    }

    public function packN($d, $n) {
        switch ($n) {
            case 1:
                if ($d > 255) return false;
                $r = chr($d);
                break;
            case 2:
                if ($d > 65535) return false;
                $r = pack('n', $d);
                break;
            case 3:
                if ($d > 16777215) return false;
                $hb = floor($d / 65536);
                $r = chr($hb) . pack('n', $d - $hb * 65536);
                break;
            case 4:
                if ($d > 4294967295) return false;
                $r = pack('N', $d);
                break;
            case 5:
                if ($d >= 1099511627776) return false;
                $hb = floor($d / 4294967296);
                $r = chr($hb) . pack('N', $d - $hb * 4294967296);
            default:
                return false;
        }
        return $r;
    }

    public function unpackN($d)
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
    public function unpackIdx($idx)
    {
        $o_l = $this->bytes_for_offset;
        $r_l = $this->bytes_for_length;
        if (strlen($idx) !== $o_l + $r_l) return false;
        $d_off = substr($idx, 0, $o_l);
        $l_off = substr($idx, -$r_l);
        return [
            'offset' => $this->unpackN($d_off),
            'length' => $this->unpackN($l_off)
        ];
    }
}