<?php
namespace ierusalim\FileRecords;

/**
 * Rows: 64 bytes each
 *  [0] 4 - last-time  or 0 for free cell
 *  [4] 1 - progress
 *  [5] 8 - tmp name
 * [13] 1 - len of name
 * [14] 50 - file name
 */

class nameSquat
{
    public $base_path;
    public $fr = false;
    public $np_rows = 50;
    public $np_file = 'namesquat.tmp';

    public $content = false;

    public function getFr()
    {
        if ($this->fr === false) {
            $this->fr = new FileRecords($this->base_path . $this->np_file, 64);
        }
        return $this->fr;
    }

    public function __construct($base_path = '.', $np_rows = 50)
    {
        $base_path = realpath($base_path);
        if ($base_path === false) {
            throw new \Exception('Path not found');
        }
        $this->base_path = $base_path . DIRECTORY_SEPARATOR;
        $this->np_rows = $np_rows;
    }

    public function getContent($reload = false)
    {
        if ($reload || ($this->content === false)) {
            $file_name = $this->base_path . $this->np_file;
            $file_size = $this->np_rows * 64;
            if (is_file($file_name)) {
                $content = file_get_contents($file_name);
                if (strlen($content) != $file_size) {
                    throw new \Exception('nameProgress file corrupted');
                }
            } else {
                $content = str_repeat(chr(0), $file_size);
                $res = file_put_contents($file_name, $content);
                if ($res !== $file_size) {
                    throw new \Exception("Can't create nameProgress file");
                }
            }
            $this->content = $content;
        }
        return $this->content;
    }

    public function getAll($reload = false)
    {
        $content = $this->getContent($reload);
        $arr = [];
        for($cell = 0; $cell < $this->np_rows; $cell++) {
            $pt = $cell * 64;
            $time = unpack('N', substr($content, $pt, 4))[1];
            if ($time) {
                $prog = ord($content[$pt + 4]);
                $tmp = substr($content, $pt + 5, 8);
                $l = ord($content[$pt + 13]);
                $file_name = substr($content, $pt + 14, $l);
                $arr[$file_name] = compact('cell', 'time', 'prog', 'tmp');
            }
        }
        return $arr;

    }
    public function nameScan($file_name)
    {
        $l = strlen($file_name);
        if ($l>50) {
            throw new \Exception("File name too long");
        }
        $scn = chr($l) . $file_name;
        $l++;

        $content = $this->getContent(true);

        $free = false;
        for($cell = 0; $cell < $this->np_rows; $cell++) {
            $pt = $cell * 64;
            if ($free === false) {
                $time = substr($content, $pt, 4);
                $time = unpack('N', $time)[1];
                if (!$time) {
                    $free = $cell;
                }
            }
            if (substr($content, $pt + 13, $l) == $scn) {
                $time = substr($content, $pt, 4);
                $time = unpack('N', $time)[1];
                $prog = ord($content[$pt + 4]);
                $tmp = substr($content, $pt + 5, 8);
                return compact('cell', 'time', 'prog', 'tmp');
            }
        }
        return compact('free');
    }

    public function nameSquat($file_name, $time_drop, $time_offset = 0)
    {
        $stat = $this->nameScan($file_name);
        $old_tmp = false;
        if (isset($stat['free'])) {
            $cell = $stat['free'];
            if ($cell === false) {
                return "No free cells";
            }
        } else {
            $cell = $stat['cell'];
            $time = $stat['time'];
            if ($time + $time_drop >= time()) {
                return 'busy[' . $cell . ']=' . $time;
            } else {
                $old_tmp = $stat['tmp'];
            }
        }
        $tmp = $this->genTmp();

        $fr = $this->getFr();
        $rec = pack('N', time() + $time_offset) . chr(0) . $tmp
            .  chr(strlen($file_name)) . $file_name;
        //$rec = str_pad($rec, 64, chr(0), STR_PAD_RIGHT);
        $ans = $fr->reWriteRecord($cell, $rec, true);
        $this->content = false;
        if ($ans === $cell) {
            return compact('cell', 'tmp', 'old_tmp');
        } else {
            return $ans;
        }
    }

    public function readCell($cell)
    {
        $fr = $this->getFr();
        $rec = $fr->readRecord($cell);
        if (($rec !== false) && strlen($rec) == 64) {
            $time = unpack('N', substr($rec, 0, 4))[1];
            $prog = ord($rec[4]);
            $tmp = substr($rec, 5, 8);
            $l = ord($rec[13]);
            $name = substr($rec, 14, $l);
            $rec = compact('time', 'prog', 'tmp', 'name');
        }
        return $rec;
    }

    public function updateProgress($cell, $progByte = 0, $time_offset = 0)
    {
        $this->content = false;
        $fr = $this->getFr();
        $rec = pack('N', time() + $time_offset) . chr($progByte);
        return $fr->reWriteRecord($cell, $rec, true);
    }

    public function releaseCell($cell)
    {
        $this->content = false;
        $fr = $this->getFr();
        $rec = pack('N', 0);
        return $fr->reWriteRecord($cell, $rec, true);
    }

    public function genTmp($len = 8)
    {
        $tmp = '';
        for($i=0; $i<$len; $i++) {
            $tmp .= chr(mt_rand(0x61, 0x7a));
        }
        return $tmp;
    }
}