<?php

namespace Swlib\Table;

enum JoinEnum: string
{
    case LEFT = ' LEFT JOIN ';
    case RIGHT = ' RIGHT JOIN ';
    case INNER = ' INNER JOIN ';
}
