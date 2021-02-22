<?php
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testHumanReadableFileSize()
    {

        $this->assertSame("1 B", \PassHub\Utils::humanReadableFileSize(1));
        $this->assertSame("1 KB", \PassHub\Utils::humanReadableFileSize(1024));
        $this->assertSame("1 MB", \PassHub\Utils::humanReadableFileSize(1024 * 1024));

    }
}
