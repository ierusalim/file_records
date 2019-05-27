<?php
namespace ierusalim\FileRecords;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2019-05-21 at 13:31:35.
 */
define("TMP_PATH", 'tmp' . DIRECTORY_SEPARATOR);

class FileRecordsTest extends \PHPUnit_Framework_TestCase
{
    public $test_file = TMP_PATH . 'testfile.txt';
    public $test_len =32;
    /**
     * @var FileRecords
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        if (!is_dir(TMP_PATH)) {
            if (!@\mkdir(TMP_PATH)) {
                $this->fail("Can't create temporary directory " . TMP_PATH);
            }
        }
        $this->object = new FileRecords($this->test_file, $this->test_len);
    }

    public function delTestFile()
    {
        // remove test_file if exists
        if (file_exists($this->test_file)) {
            unlink($this->test_file);
        }
    }
    public function createTestFileWithRecords($o, $rec_cnt)
    {
        $this->delTestFile();

        $rec = [];
        $sum = '';

        for($rn = 0; $rn < $rec_cnt; $rn++) {
            $rec[$rn] = str_repeat(chr(rand(64,100)), $this->test_len);
            $rec_num = $o->appendRecord($rec[$rn]);
            $this->assertEquals($rn, $rec_num);
        }

        $f_content = file_get_contents($this->test_file);
        $this->assertEquals(implode($rec), $f_content);
        return $rec;
    }
    public function testConstructor()
    {
        // create test-file with 2 records
        $o = new FileRecords($this->test_file, $this->test_len);
        $this->createTestFileWithRecords($o, 2);

        $this->setExpectedException("\Exception");
        $x = new FileRecords($this->test_file, 0);

        $o->fclose();
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::recordsCount
     * @todo   Implement testRecordsCount().
     */
    public function testRecordsCount()
    {
        // create test-file with 10 records
        $o = $this->object;
        $this->createTestFileWithRecords($o, 10);

        $rec_cnt = $o->recordsCount();
        $this->assertEquals(10, $rec_cnt);

        $o->fclose();
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::readRecord
     * @todo   Implement testReadRecord().
     */
    public function testReadRecord()
    {
        // create test-file with 5 records
        $o = $this->object;
        $rec_arr = $this->createTestFileWithRecords($o, 10);

        foreach($rec_arr as $rec_num => $rec_data) {
            $r = $o->readRecord($rec_num);
            $this->assertEquals($rec_data, $r);
        }

        // test large record number
        $r = $o->readRecord(11);
        $this->assertFalse($r);

        // test bad record number
        $r = $o->readRecord(-1);
        $this->assertFalse($r);

        //remove file and try to read
        $o->fclose();

        $this->delTestFile();

        $this->setExpectedException("\Exception");
        $r = $o->readRecord(1);
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::fclose
     * @todo   Implement testFclose().
     */
    public function testFclose()
    {
        // create test-file with 1 record
        $o = $this->object;
        $this->createTestFileWithRecords($o, 1);

        // now file is open
        $this->assertNotEmpty($o->f);

        $o->fclose();

        // now file must be closed
        $this->assertFalse($o->f);
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::fopen
     * @todo   Implement testFopen().
     */
    public function testFopen()
    {
        // remove test file
        $this->delTestFile();

        $o = $this->object;

        // Open empty file for reWrite - must return false
        $f = $o->fopen('w');
        $this->assertFalse($f);

        // Open empty file for Append - must return Resoures
        $f = $o->fopen('a');
        $this->assertTrue(is_resource($f));

        // Write 1 record to file
        $rec = str_repeat('A', $this->test_len);
        $o->appendRecord($rec);

        $rcnt = $o->recordsCount();
        $this->assertEquals(1, $rcnt);

        // Try to read this record
        $back = $o->readRecord(0);
        $this->assertEquals($rec, $back);

        // try to add new record
        $rec2 = str_repeat('B', $this->test_len);
        $n = $o->appendRecord($rec2);
        $this->assertEquals(1, $n);

        // Try to open for read
        $f = $o->fopen('r');
        $this->assertTrue(is_resource($f));

        // Open to reWrite
        $f = $o->fopen('w');
        $this->assertTrue(is_resource($f));

        $n = $o->reWriteRecord(1, $rec);
        $this->assertEquals(1, $n);

        // remove file and try to open for read
        $o->fclose();
        $this->delTestFile();
        $z = $o->fopen('r');
        $this->assertFalse($z);

        // Try illegal mode
        $this->setExpectedException("\Exception");
        $r = $o->fopen('x');
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::appendRecord
     * @todo   Implement testAppendRecord().
     */
    public function testAppendRecord()
    {
        $o = $this->object;
        $rcnt = $o->recordsCount();

        $data = str_repeat('M', $this->test_len);
        $o->appendRecord($data);

        $rcnt2 = $o->recordsCount();

        $this->assertEquals($rcnt + 1, $rcnt2);

        $o->fclose();

        $ans = $o->appendRecord($data);
        $rcnt3 = $o->recordsCount();
        $this->assertEquals($rcnt + 2, $rcnt3);
        $this->assertEquals($rcnt3-1,$ans);

        // File Limit test
        $o->file_size_limit = $o->file_size;
        $ans = $o->appendRecord($data);
        $this->assertEquals("FILE_SIZE_LIMIT is reached", $ans);
        $o->file_size_limit = false;

        // make problem
        $f = $o->f;
        fclose($f);
        $f = fopen($this->test_file, 'rb');
        $this->assertTrue(is_resource($f));

        // try add next record
        $ans = $o->appendRecord($data);
        $this->assertTrue(is_string($ans));
        $rcnt4 = $o->recordsCount();

        $this->assertEquals($rcnt3, $rcnt4);

        // try write to file with bad file name
        $x = new FileRecords('bad &*file|', $this->test_len);
        $ans = $x->appendRecord($data);
//        $this->assertTrue(is_string($ans));

        // Try exception
        $this->setExpectedException("\Exception");
        $ans = $o->appendRecord("abc");
    }

    /**
     * @covers ierusalim\FileRecords\FileRecords::reWriteRecord
     * @todo   Implement testReWriteRecord().
     */
    public function testReWriteRecord()
    {
        // create test-file with 10 records
        $o = $this->object;
        $this->createTestFileWithRecords($o, 10);

        $rec_cnt = $o->recordsCount();
        $this->assertEquals(10, $rec_cnt);

        $rec = str_repeat('X', $this->test_len);
        $n = $o->reWriteRecord(5, $rec);
        $this->assertEquals(5, $n);

        $back = $o->readRecord(5);
        $this->assertEquals($rec, $back);

        $n = $o->reWriteRecord($rec_cnt, $rec);
        $this->assertTrue(is_string($n));

        // make problem
        $f = $o->f;
        fclose($f);
        $f = fopen($this->test_file, 'rb');
        $this->assertTrue(is_resource($f));

        $n = $o->reWriteRecord(5, $rec);
        $this->assertTrue(is_string($n));
        $o->fclose();

        $this->setExpectedException("\Exception");
        $n = $o->reWriteRecord($rec_cnt, 'abc');
    }
}
