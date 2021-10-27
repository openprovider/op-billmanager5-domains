<?php

use Billmgr\Api;

abstract class TransferException extends ClientException
{
    /**
     * @param $itemId
     * @param Database $dbClient
     * @throws Exception
     */
    public function cancelTransfer($itemId, $dbClient) {
        $expenseIds = $this->getExpenseByItemId($itemId, $dbClient);
        foreach ($expenseIds as $expenseId) {
            Api::deleteExpense($expenseId);
        }
        Api::postClose($itemId);
    }

    /**
     * @param $itemId
     * @param Database $dbClient
     * @return array
     * @throws Exception
     */
    private function getExpenseByItemId($itemId, $dbClient ) {
        $expenseIds = [];
        if( trim($itemId) == "" ){

            throw new \Exception("Cancel transfer order failed: wrong item ID");
        }
        $result = $dbClient->query("SELECT `id` FROM `expense` WHERE `item` = '".$dbClient->escape($itemId)."' LIMIT 1");
        if( $result instanceof mysqli_result ) {
            if ($result->num_rows !== 0) {
                while ($row = $result->fetch_assoc()) {
                    $expenseIds[] = $row["id"];
                }
            }
        } else {
            throw new \Exception("Cancel transfer order failed: expenses query failed");
        }
        return $expenseIds;
    }
}