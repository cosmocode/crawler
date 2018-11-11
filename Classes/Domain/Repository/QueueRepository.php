<?php
namespace AOE\Crawler\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use AOE\Crawler\Domain\Model\Process;
use AOE\Crawler\Domain\Model\Queue;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class QueueRepository
 *
 * @package AOE\Crawler\Domain\Repository
 */
class QueueRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $tableName = 'tx_crawler_queue';

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder = \Doctrine\DBAL\Query\QueryBuilder::class;

    /**
     * QueueRepository constructor.
     */
    public function __construct()
    {
        $this->queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
    }

    /**
     * This method is used to find the youngest entry for a given process.
     *
     * @param Process $process
     *
     * @return Queue $entry
     */
    public function findYoungestEntryForProcess(Process $process)
    {
        return $this->getFirstOrLastObjectByProcess($process, 'exec_time');
    }

    /**
     * This method is used to find the oldest entry for a given process.
     *
     * @param Process $process
     *
     * @return Queue
     */
    public function findOldestEntryForProcess(Process $process)
    {
        return $this->getFirstOrLastObjectByProcess($process, 'exec_time', 'DESC');
    }

    /**
     * This internal helper method is used to create an instance of an entry object
     *
     * @param Process $process
     * @param string $orderByField first matching item will be returned as object
     * @param string $orderBySorting sorting direction
     *
     * @return Queue
     */
    protected function getFirstOrLastObjectByProcess($process, $orderByField, $orderBySorting = 'ASC')
    {

        $resultObject = new \stdClass();
        $first = $this->queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_id_completed', $this->queryBuilder->createNamedParameter($process->getProcess_id())),
                $this->queryBuilder->expr()->gt('exec_time', 0)
            )
            ->setMaxResults(1)
            ->addOrderBy($orderByField, $orderBySorting)
            ->execute();

        while ($row = $first->fetch()) {
            $resultObject = new Queue($row);
        }

        return $resultObject;
    }

    /**
     * Counts all executed items of a process.
     *
     * @param Process $process
     *
     * @return int
     */
    public function countExecutedItemsByProcess($process)
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_id_completed', $this->queryBuilder->createNamedParameter($process->getProcess_id())),
                $this->queryBuilder->expr()->gt('exec_time', 0)
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * Counts items of a process which yet have not been processed/executed
     *
     * @param Process $process
     *
     * @return int
     */
    public function countNonExecutedItemsByProcess($process)
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_id', $this->queryBuilder->createNamedParameter($process->getProcess_id())),
                $this->queryBuilder->expr()->eq('exec_time', 0)
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * Count items which have not been processed yet
     * 
     * @return int
     */
    public function countUnprocessedItems()
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_scheduled', 0),
                $this->queryBuilder->expr()->eq('exec_time', 0),
                $this->queryBuilder->expr()->lte('scheduled', 0)
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * This method can be used to count all queue entrys which are
     * scheduled for now or a earlier date.
     *
     * @return int
     */
    public function countAllPendingItems()
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_scheduled', 0),
                $this->queryBuilder->expr()->eq('exec_time', 0),
                $this->queryBuilder->expr()->lte('scheduled', time())
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * This method can be used to count all queue entrys which are
     * scheduled for now or a earlier date and are assigned to a process.
     *
     * @return int
     */
    public function countAllAssignedPendingItems()
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->neq('process_id', '""'),
                $this->queryBuilder->expr()->eq('exec_time', 0),
                $this->queryBuilder->expr()->lte('scheduled', time())
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * This method can be used to count all queue entrys which are
     * scheduled for now or a earlier date and are not assigned to a process.
     *
     * @return int
     */
    public function countAllUnassignedPendingItems()
    {
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('process_id', '""'),
                $this->queryBuilder->expr()->eq('exec_time', 0),
                $this->queryBuilder->expr()->lte('scheduled', time())
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * Count pending queue entries grouped by configuration key
     *
     * @return array
     */
    public function countPendingItemsGroupedByConfigurationKey()
    {

        $db = $this->getDB();
        $res = $db->exec_SELECTquery(
            "configuration, count(*) as unprocessed, sum(process_id != '') as assignedButUnprocessed",
            $this->tableName,
            'exec_time = 0 AND scheduled < ' . time(),
            'configuration'
        );
        $rows = [];
        while ($row = $db->sql_fetch_assoc($res)) {
            $rows[] = $row;
        }

        return $rows;

/**
        $count = $this->queryBuilder
            ->count('*')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->eq('exec_time', 0),
                $this->queryBuilder->expr()->lte('scheduled', time())
            )
            ->addGroupBy(['configuration'])
            ->execute()
            ->fetchColumn(0);

        return $count;
**/
    }

    /**
     * Get set id with unprocessed entries
     *
     * @param void
     *
     * @return array array of set ids
     */
    public function getSetIdWithUnprocessedEntries()
    {
        $statement = $this->queryBuilder
            ->select('set_id')
            ->from($this->tableName)
            ->where(
                $this->queryBuilder->expr()->lt('scheduled', time()),
                $this->queryBuilder->expr()->eq('exec_time', 0)
            )
            ->addGroupBy('set_id')
            ->execute();

        $setIds = [];
        while ($row = $statement->fetch()) {
            $setIds[] = intval($row['set_id']);
        }

        return $setIds;
    }

    /**
     * Get total queue entries by configuration
     *
     * @param array $setIds
     *
     * @return array totals by configuration (keys)
     */
    public function getTotalQueueEntriesByConfiguration(array $setIds)
    {
        $totals = [];
        if (count($setIds) > 0) {
            $db = $this->getDB();
            $res = $db->exec_SELECTquery(
                'configuration, count(*) as c',
                $this->tableName,
                'set_id in (' . implode(',', $setIds) . ') AND scheduled < ' . time(),
                'configuration'
            );
            while ($row = $db->sql_fetch_assoc($res)) {
                $totals[$row['configuration']] = $row['c'];
            }
        }

        return $totals;
    }

    /**
     * Get the timestamps of the last processed entries
     *
     * @param int $limit
     *
     * @return array
     */
    public function getLastProcessedEntriesTimestamps($limit = 100)
    {
        $statement = $this->queryBuilder
            ->select('exec_time')
            ->from($this->tableName)
            ->addOrderBy('exec_time', 'desc')
            ->setMaxResults($limit)
            ->execute();

        $rows = [];
        while ($row = $statement->fetch()) {
            $rows[] = $row['exec_time'];
        }

        return $rows;
    }

    /**
     * Get the last processed entries
     *
     * @param int $limit
     *
     * @return array
     */
    public function getLastProcessedEntries($selectFields = '*', $limit = 100)
    {
        $db = $this->getDB();
        $res = $db->exec_SELECTquery(
            $selectFields,
            $this->tableName,
            '',
            '',
            'exec_time desc',
            $limit
        );

        $rows = [];
        while (($row = $db->sql_fetch_assoc($res)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Get performance statistics data
     *
     * @param int $start timestamp
     * @param int $end timestamp
     *
     * @return array performance data
     */
    public function getPerformanceData($start, $end)
    {
        $db = $this->getDB();
        $res = $db->exec_SELECTquery(
            'process_id_completed, min(exec_time) as start, max(exec_time) as end, count(*) as urlcount',
            $this->tableName,
            'exec_time != 0 and exec_time >= ' . intval($start) . ' and exec_time <= ' . intval($end),
            'process_id_completed'
        );

        $rows = [];
        while (($row = $db->sql_fetch_assoc($res)) !== false) {
            $rows[$row['process_id_completed']] = $row;
        }

        return $rows;
    }

    /**
     * This method is used to count all processes in the process table.
     *
     * @param  string $where Where clause
     *
     * @return integer
     */
    public function countAll($where = '1 = 1')
    {
        return $this->countByWhere($where);
    }
}
