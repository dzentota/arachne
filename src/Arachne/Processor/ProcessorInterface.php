<?php
declare(strict_types=1);

namespace Arachne\Processor;

use Arachne\ResultSet;

interface ProcessorInterface
{
    public function processResultSet(ResultSet $resultSet): ResultSet;
}