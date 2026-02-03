<?php

namespace Swlib\DevTool\Ctrls;

use Generate\ConfigEnum;
use Generate\DatabaseConnect;
use Swlib\Controller\Abstract\AbstractController;
use Swlib\Controller\Language\Enum\LanguageEnum;
use Swlib\Exception\AppException;
use Swlib\Response\TwigResponse;
use Swlib\Router\Router;
use Throwable;

/**
 * 项目是 protobuf 驱动的
 * protobuf 文件是 根据数据库生成的，
 * 如果需要扩充一些字段，又不添加数据库字段；
 * 可以通过这个 web 网页工具来扩展 protobuf 字段
 */
class ProtobufExtEditor extends AbstractController
{

    private array $baseTypes = [
        'int32', 'int64', 'uint32', 'uint64', 'sint32', 'sint64',
        'fixed32', 'fixed64', 'sfixed32', 'sfixed64',
        'bool', 'string', 'bytes', 'float', 'double',
    ];

    #[Router(method: 'GET')]
    public function index(): TwigResponse
    {
        if (!$this->checkDevEnvironment()) {
            return TwigResponse::render('devtool/protobuf_ext_denied.twig');
        }

        return $this->renderTableList();
    }

    /**
     * @throws AppException
     * @throws Throwable
     */
    #[Router(method: 'GET')]
    public function edit(): TwigResponse
    {
        if (!$this->checkDevEnvironment()) {
            return TwigResponse::render('devtool/protobuf_ext_denied.twig');
        }

        $dbName = (string)$this->get('db', '数据库名称不能为空');
        $table = (string)$this->get('table', '表名不能为空');

        return $this->renderEditForm($dbName, $table);
    }

    /**
     * @throws AppException
     * @throws Throwable
     */
    #[Router(method: 'POST')]
    public function save(): TwigResponse
    {
        if (!$this->checkDevEnvironment()) {
            return TwigResponse::render('devtool/protobuf_ext_denied.twig');
        }

        $dbName = (string)$this->post('db', '数据库名称不能为空');
        $table = (string)$this->post('table', '表名不能为空');

        $rows = $this->parsePostedRows();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $row['pos'] . ':' . $row['field'] . ':' . $row['type'];
        }

        // 如果用户清空了所有配置，保存空的扩展信息（清空 comment）
        // 否则保存 JSON 格式的扩展配置
        $comment = '';
        if (!empty($items)) {
            $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $comment = 'protobuf:ext:json:' . $json;
        }

        DatabaseConnect::call(function ($mysqli) use ($table, $comment) {
            $tableEscaped = $mysqli->real_escape_string($table);
            $commentEscaped = $mysqli->real_escape_string($comment);
            $sql = "SHOW FULL COLUMNS FROM `$tableEscaped` WHERE Field = 'id'";
            $res = $mysqli->query($sql);
            $row = $res?->fetch_assoc();
            if (!$row) {
                throw new AppException(LanguageEnum::DEV_TABLE_NO_ID_FIELD_WITH_NAME . ": $tableEscaped");
            }
            $type = $row['Type'];
            $null = $row['Null'] === 'NO' ? 'NOT NULL' : '';
            $default = $row['Default'] !== null ? "DEFAULT '" . $mysqli->real_escape_string((string)$row['Default']) . "'" : '';
            $extra = $row['Extra'];
            $alter = "ALTER TABLE `$tableEscaped` MODIFY COLUMN `id` $type $null $default $extra COMMENT '$commentEscaped'";
            $mysqli->query($alter);
        }, $dbName);

        return TwigResponse::render('devtool/protobuf_ext_saved.twig', [
            'db' => $dbName,
            'table' => $table,
        ]);
    }

    private function checkDevEnvironment(): bool
    {
        return ConfigEnum::APP_PROD === false;
    }

    private function renderTableList(): TwigResponse
    {
        $dbTables = [];

        DatabaseConnect::eachDbName(
        /**
         * @throws Throwable
         */ function ($dbName) use (&$dbTables) {
            $tables = [];
            DatabaseConnect::call(function ($mysqli) use (&$tables) {
                $res = $mysqli->query('SHOW TABLES');
                while ($row = $res->fetch_array()) {
                    $tables[] = $row[0];
                }
            }, $dbName);
            $dbTables[$dbName] = $tables;
        });

        return TwigResponse::render('devtool/protobuf_ext_index.twig', [
            'dbTables' => $dbTables,
        ]);
    }

    /**
     * @throws Throwable
     */
    private function renderEditForm(string $dbName, string $table): TwigResponse
    {
        $fields = DatabaseConnect::query('SHOW FULL COLUMNS FROM `' . $table . '`', $dbName)->fetch_all(MYSQLI_ASSOC);
        $idComment = '';
        foreach ($fields as $field) {
            if ($field['Field'] === 'id') {
                $idComment = (string)($field['Comment'] ?? '');
                break;
            }
        }

        $rows = [];
        if (!empty($idComment) && str_contains($idComment, 'protobuf:ext:json:')) {
            $json = str_replace('protobuf:ext:json:', '', $idComment);
            $json = trim($json);
            $arr = json_decode($json, true);
            if (is_array($arr)) {
                foreach ($arr as $item) {
                    $parts = explode(':', (string)$item, 3);
                    if (count($parts) !== 3) continue;
                    $rows[] = [
                        'pos' => trim($parts[0]),
                        'field' => trim($parts[1]),
                        'type' => trim($parts[2]),
                    ];
                }
            }
        }

        $dbCamel = DatabaseConnect::getNamespace($dbName);
        $protoTypes = $this->scanProtoTypes($dbCamel);

        return TwigResponse::render('devtool/protobuf_ext_edit.twig', [
            'db' => $dbName,
            'table' => $table,
            'rows' => $rows,
            'baseTypes' => $this->baseTypes,
            'protoTypes' => $protoTypes,
        ]);
    }

    /**
     * @return array<int, array{pos:string,field:string,type:string}>
     * @throws AppException
     */
    private function parsePostedRows(): array
    {
        // 如果用户清空了所有配置，这些参数可能不存在，提供空数组作为默认值
        $posList = (array)$this->post('pos', '', []);
        $fieldList = (array)$this->post('field', '', []);
        $typeList = (array)$this->post('type', '', []);

        $rows = [];
        $count = count($posList);
        for ($i = 0; $i < $count; $i++) {
            $pos = trim((string)($posList[$i] ?? ''));
            $field = trim((string)($fieldList[$i] ?? ''));
            $type = trim((string)($typeList[$i] ?? ''));
            if ($pos === '' || $field === '' || $type === '') {
                continue;
            }
            if ($pos !== 'item' && $pos !== 'lists') {
                throw new AppException(LanguageEnum::DEV_POSITION_INVALID);
            }
            $rows[] = ['pos' => $pos, 'field' => $field, 'type' => $type];
        }

        return $rows;
    }

    /**
     * 扫描当前数据库对应 proto 目录下的可选 message 类型
     * 生成形如 Protobuf.Wenyuehui.LiveMembershipLevels.LiveMembershipLevelsProto 的类型字符串
     *
     * @return string[]
     */
    private function scanProtoTypes(string $dbCamel): array
    {
        $dir = ROOT_DIR . 'protos/' . $dbCamel;
        if (!is_dir($dir)) {
            return [];
        }

        $types = [];
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (!str_ends_with($file, '.proto')) {
                continue;
            }

            $base = substr($file, 0, -6);
            // 排除 BaseExt 文件
            if ($base === 'BaseExt' || $base === $dbCamel . 'BaseExt') {
                continue;
            }

            $types[] = 'Protobuf.' . $dbCamel . '.' . $base . '.' . $base . 'Proto';
        }

        sort($types);
        return $types;
    }
}
