<?php
/**
 * CriteriaPlugin for phplist
 * 
 * This file is a part of CriteriaPlugin.
 *
 * CriteriaPlugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * CriteriaPlugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * @category  phplist
 * @package   CriteriaPlugin
 * @author    Duncan Cameron
 * @copyright 2014 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

/**
 * DAO class that encapsulates the database access
 * 
 * @category  phplist
 * @package   CriteriaPlugin
 */
class SegmentPlugin_DAO extends CommonPlugin_DAO
{

    public function selectData(array $attribute)
    {
        $tableName = $this->table_prefix . 'listattr_' . $attribute['tablename'];

        return $this->dbCommand->queryAll(<<<END
            SELECT id, name
            FROM $tableName
            ORDER BY listorder, id
END
        );
        return $this->dbCommand->queryAll($sql);
    }

    /**
     * Retrieves campaigns
     * @param string $loginId login id of the current admin
     * @param int $max Maximum number of campaigns to be returned
     * @return Iterator
     * @access public
     */

    public function campaigns($loginId, $max)
    {
        $owner = $loginId ? "AND m.owner = $loginId" : '';
        $limitClause = is_null($max) ? '' : "LIMIT $max";

        $sql = "SELECT m.id, CONCAT_WS(' - ',m.subject, DATE_FORMAT(m.sent,'%d/%m/%y')) AS subject
            FROM {$this->tables['message']} m
            WHERE m.status = 'sent'
            $owner
            ORDER BY m.sent DESC
            $limitClause
            ";
        return $this->dbCommand->queryAll($sql);
    }

    public function deleteNotSent($campaign)

    {
        $sql = "DELETE FROM {$this->tables['usermessage']}
            WHERE status = 'not sent'
            AND messageid = $campaign
        ";
        return $this->dbCommand->queryAffectedRows($sql);
    }
    /*
     *  Methods for each subscriber data type
     */ 
    public function emailSubquery($operator, $value)
    {
        $value = sql_escape($value);

        switch ($operator) {
            case 'matches':
                $op = 'LIKE';
                break;
            case 'notmatches':
                $op = 'NOT LIKE';
                break;
            case 'is':
            default:
                $op = '=';
        }
            
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']}
            WHERE email $op '$value'
END;
        return $sql;
    }

    public function enteredSubquery($operator, $value)
    {
        $value = sql_escape($value);
        $op = $operator == 'is' ? '=' 
            : ($operator == 'before' ? '<' : '>');
            
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']}
            WHERE entered $op '$value'
END;
        return $sql;
    }

    public function activitySubquery($operator, $value)
    {
        $op = $operator == 'opened' ? 'IS NOT NULL' : 'IS NULL';
        $sql = <<<END
            SELECT um.userid AS id
            FROM {$this->tables['usermessage']} um
            WHERE um.viewed $op
            AND um.messageid = $value
END;
        return $sql;
    }
    /*
     *  Methods for each type of attribute
     */ 
    public function textSubquery($attributeId, $operator, $target)
    {
        $target = sql_escape($target);

        switch ($operator) {
            case 'isnot':
                $op = '!=';
                break;
            case 'blank':
                $op = '=';
                $target = '';
                break;
            case 'notblank':
                $op = '!=';
                $target = '';
                break;
            case 'is':
            default:
                $op = '=';
                break;
        }
            
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']} u
            LEFT JOIN {$this->tables['user_attribute']} ua ON u.id = ua.userid AND ua.attributeid = $attributeId 
            WHERE COALESCE(value, '') $op '$target'
END;
        return $sql;
    }

    public function selectSubquery($attributeId, $operator, $target)
    {
        $op = $operator == 'is' ? '=' : '!=';
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']} u
            LEFT JOIN {$this->tables['user_attribute']} ua ON u.id = ua.userid AND ua.attributeid = $attributeId 
            WHERE COALESCE(value, 0) $op $target
END;
        return $sql;
    }

    public function dateSubquery($attributeId, $operator, $target)
    {
        $target = sql_escape($target);
        $op = $operator == 'is' ? '=' 
            : ($operator == 'before' ? '<' : '>');
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']} u
            LEFT JOIN {$this->tables['user_attribute']} ua  ON u.id = ua.userid AND ua.attributeid = $attributeId 
            WHERE COALESCE(value, '') != '' AND COALESCE(value, '') $op '$target'
END;
        return $sql;
    }

    public function checkboxSubquery($attributeId, $operator, $target)
    {
        $op = $operator == 'is' ? '=' : '!=';
        $sql = <<<END
            SELECT id
            FROM {$this->tables['user']} u
            LEFT JOIN {$this->tables['user_attribute']} ua ON u.id = ua.userid AND ua.attributeid = $attributeId 
            WHERE COALESCE(value, '') $op 'on'
END;
        return $sql;
    }

    public function subscribers(array $subquery)
    {
        $from = "($subquery[0]) AS T0\n";
        $n = 0;

        foreach (array_slice($subquery, 1) as $s) {
            ++$n;
            $from .= "JOIN ($s) AS T$n ON T0.id = T$n.id\n";
        }
        $sql = <<<END
            SELECT T0.id AS id
            FROM $from
END;
        return $this->dbCommand->queryColumn($sql, 'id');
    }

//~ (SELECT id FROM `phplist_user_user`
//~ where id in (1,3))
//~ union
 //~ (SELECT id FROM `phplist_user_user`
//~ where id in (1,4))

}
