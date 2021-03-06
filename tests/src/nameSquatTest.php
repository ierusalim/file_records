<?php
namespace ierusalim\FileRecords;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2019-06-06 at 09:21:50.
 */
class nameSquatTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var nameSquat
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new nameSquat('.');
    }

    protected function tearDown()
    {
        // remove file if created
        $np = $this->object;
        $fr = $np->getFr();
        $file_name = $fr->file_name;
        $fr->fclose();
        if (is_file($file_name)) {
            unlink($file_name);
        }
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::getFr
     * @todo   Implement testGetFr().
     */
    public function testGetFr()
    {
        $np = $this->object;
        $fr = $np->getFr();
        $this->assertEquals(64, $fr->rec_size);
    }

    public function testConstruct()
    {
        $np = new nameSquat(getcwd());
        $path1 = $np->base_path;
        $np2 = new nameSquat(getcwd() . DIRECTORY_SEPARATOR);
        $path2 = $np->base_path;
        $this->assertEquals($path1, $path2);

        // test relative path
        $np = new nameSquat('src');
        $path = $np->base_path;
        $this->assertEquals(getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR, $path);

        $this->setExpectedException("\Exception");
        $np = new nameSquat('/abc/');
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::getContent
     * @todo   Implement testGetContent().
     */
    public function testGetContent()
    {
        $np = $this->object;

        // Create
        $content = $np->getContent();
        $this->assertEquals(64 * $np->np_rows, strlen($content));

        // Read from cache
        $content = $np->getContent();
        $this->assertEquals(64 * $np->np_rows, strlen($content));

        // Reload from file
        $content = $np->getContent(true);
        $this->assertEquals(64 * $np->np_rows, strlen($content));
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::getAll
     * @todo   Implement testGetAll().
     */
    public function testGetAll()
    {
        $np = $this->object;

        $arr = $np->getAll();
        $this->assertEquals([], $arr);

        // create row
        $r = $np->squat('test', 1);

        $arr = $np->getAll();
        $this->assertArrayHasKey('test', $arr);
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::nameScan
     * @todo   Implement testNameScan().
     */
    public function testNameScan()
    {
        $np = $this->object;

        // create name 'test'
        $r = $np->squat('test', 1);
        $r = $np->squat('test2', 1);

        $arr = $np->nameScan('test');
        $this->assertArrayHasKey('cell', $arr);
        $cell = $arr['cell'];
        $this->assertTrue(is_numeric($cell));

        $arr = $np->nameScan('test2');
        $this->assertArrayHasKey('cell', $arr);
        $cell2 = $arr['cell'];
        $this->assertTrue(is_numeric($cell2));

        $this->assertNotEquals($cell, $cell2);

        // long name error
        $this->setExpectedException("\Exception");
        $arr = $np->nameScan(str_repeat('A', 51));
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::squat
     * @todo   Implement testSquat().
     */
    public function testSquat()
    {
        $np = $this->object;

        $base_name = 'test';

        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('cell', $arr);
            $cell = $arr['cell'];
            $this->assertTrue(is_numeric($cell));
        }

        // no free cells
        $str = $np->squat($base_name . $cell, 1);
        $this->assertEquals('No free cells', $str);

        // test all names busy
        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $str = $np->squat($base_name . $cell, 2);
            $this->assertEquals('busy', substr($str, 0, 4));
        }

        echo "Sleep 3 sec...";
        sleep(3);
        echo "ok\n";

        // test all names expired
        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('old_tmp', $arr);
        }

    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::readCell
     * @todo   Implement testReadCell().
     */
    public function testReadCell()
    {
        $np = $this->object;

        $base_name = 'test';

        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('cell', $arr);
            $cell = $arr['cell'];
            $this->assertTrue(is_numeric($cell));
        }

        for ($i = 0; $i < 100; $i++) {
            $cell = mt_rand(0, $np->np_rows-1);
            $arr = $np->readCell($cell);
            $this->assertEquals($base_name . $cell, $arr['name']);
        }

    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::updateProgress
     * @todo   Implement testUpdateProgress().
     */
    public function testUpdateProgress()
    {
        $np = $this->object;

        $base_name = 'test';

        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('cell', $arr);
            $cell = $arr['cell'];
            $this->assertTrue(is_numeric($cell));
        }
        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $rec = $np->updateProgress($cell, $cell % 256);
            $back = $np->readCell($cell);
            $this->assertEquals($cell % 256, $back['prog']);
            $this->assertEquals($cell, $rec);
        }

        foreach($np->getAll() as $el) {
            $cell = $el['cell'];
            $prog = $el['prog'];
            $this->assertEquals($cell % 256, $prog);
        }
    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::releaseCell
     * @todo   Implement testReleaseCell().
     */
    public function testReleaseCell()
    {
        $np = $this->object;

        $base_name = 'test';

        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('cell', $arr);
            $cell = $arr['cell'];
            $this->assertTrue(is_numeric($cell));
        }

        // no free cells
        $str = $np->squat($base_name . $cell, 1);
        $this->assertEquals('No free cells', $str);

        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $res = $np->releaseCell($cell);
            $this->assertEquals($cell, $res);
        }

        $arr = $np->getAll();
        $this->assertEquals([], $arr);


        // squat again
        for ($cell = 0; $cell < $np->np_rows; $cell++) {
            $arr = $np->squat($base_name . $cell, 2);
            $this->assertArrayHasKey('cell', $arr);
            $cell = $arr['cell'];
            $this->assertTrue(is_numeric($cell));
        }

        // no free cells
        $str = $np->squat($base_name . $cell, 1);
        $this->assertEquals('No free cells', $str);

    }

    /**
     * @covers ierusalim\FileRecords\nameSquat::genTmp
     * @todo   Implement testGenTmp().
     */
    public function testGenTmp()
    {
        $np = $this->object;
        $t = $np->genTmp();
        $this->assertEquals(8, strlen($t));
        $t = $np->genTmp(32);
        $this->assertEquals(32, strlen($t));
    }
}
