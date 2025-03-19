<?php

namespace Swlib\Table;

enum TableEnum: string
{
    case FUNCTION_FIELD_DELIMITER = '__mysqlFuncField__';
    case AS_TABLE_DELIMITER = '__asTable__';

}
