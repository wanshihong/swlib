<?php
declare(strict_types=1);

namespace Swlib\Admin\Controller;

use Swlib\Admin\Config\ActionsConfig;
use Swlib\Admin\Config\PageConfig;
use Swlib\Admin\Config\PageFieldsConfig;
use Swlib\Admin\Enum\PagePosEnum;
use Swlib\Admin\Fields\AbstractField;
use Swlib\Admin\Fields\SelectField;
use Swlib\Admin\Manager\ListRowManager;
use Swlib\Admin\Middleware\AdminInitMiddleware;
use Swlib\Admin\Middleware\PermissionsMiddleware;
use Swlib\Admin\Utils\Func;
use Swlib\Controller\AbstractController;
use Swlib\Exception\AppException;
use Swlib\Response\JsonResponse;
use Swlib\Response\RedirectResponse;
use Swlib\Response\TwigResponse;
use Swlib\Router\Router;
use Swlib\Table\TableInterface;
use Throwable;

/**
 * 自定义查询
 * @method  join(TableInterface $query):void  查询的时候添加关联查询，每个路由都可以单独设置
 * @method  listsQuery(TableInterface $query):void  列表页面的查询，
 * @method  editQuery(TableInterface $query):void    编辑页面的自定义查询
 * @method  detailQuery(TableInterface $query):void   详情页面的自定义查询
 *
 * 页面配置
 * @method  configPage(PageConfig $config):void   配置页面相关
 * @method  configField(PageFieldsConfig $fields):void  配置字段
 * @method  configAction(ActionsConfig $actions):void  配置页面按钮
 *
 * 事件钩子
 * @method  insertBefore(TableInterface $table):void   写入之前
 * @method  insertAfter(TableInterface $table):void    写入之后
 * @method  insertUpdateBefore(TableInterface $table):void  写入和修改之前
 * @method  insertUpdateAfter(TableInterface $table):void   写入和修改之后
 * @method  updateBefore(TableInterface $table):void  更新之前
 * @method  updateAfter(TableInterface $table):void  更新之后
 * @method  deleteBefore(TableInterface $table):void  删除之前
 * @method  deleteAfter(TableInterface $table):void  删除之后
 */
#[Router(middleware: [AdminInitMiddleware::class, PermissionsMiddleware::class])]
abstract class  AbstractAdmin extends AbstractController
{

    public PageConfig $pageConfig;
    protected PageFieldsConfig $fieldsConfig;
    protected ActionsConfig $actionConfig;

    protected PagePosEnum $pagePos = PagePosEnum::INDEX_LISTS;

    protected string $listRefreshUrl = "";

    /**
     * @throws Throwable
     */
    private function Init(PagePosEnum $pos): void
    {
        $this->listRefreshUrl = Func::url('lists', [], [], false);

        $this->pagePos = $pos;
        // 页面配置
        $this->pageConfig = new PageConfig();
        $this->configPage($this->pageConfig);
        $this->pageConfig->frameworkCheckFieldsPermissions();


        // 字段配置
        $this->fieldsConfig = new PageFieldsConfig($this);
        $this->configField($this->fieldsConfig);
        $this->fieldsConfig->checkFieldsPermissions();
        Func::addStaticFilesToPage($this->pageConfig, $this->fieldsConfig->fields);


        // 配置页面按钮
        $this->actionConfig = new ActionsConfig();
        $this->configAction($this->actionConfig);
        $this->actionConfig->createDefaultAction();
        $this->actionConfig->frameworkCheckFieldsPermissions();
        Func::addStaticFilesToPage($this->pageConfig, $this->actionConfig->actions);

    }


    /**
     * @throws AppException
     */
    public function __call(string $name, array $arguments)
    {
        $funcNames = [
            // 自定义查询方法
            'join', 'listsQuery', 'editQuery', 'detailQuery',
            // 页面配置方法
            'configPage', 'configField', 'configAction',
            // 事件钩子
            'insertBefore', 'insertAfter', 'insertUpdateBefore', 'insertUpdateAfter', 'updateBefore', 'updateAfter', 'deleteBefore', 'deleteAfter'
        ];

        if (!in_array($name, $funcNames)) {
            throw new AppException("%方法不存在", $name);
        }
    }

    /**
     * @throws Throwable
     */
    #[Router(method: "GET")]
    public function lists(): TwigResponse
    {
        $this->Init(PagePosEnum::INDEX_LISTS);
        // 创建查询
        $query = new $this->pageConfig->tableName;

        $configOrder = $this->pageConfig->order;

        // 接收参数
        $queryPage = (int)$this->get('page', '', 1);
        $querySize = (int)$this->get('size', '', $this->pageConfig->querySize);
        $orderField = $this->get('order-field', '', $configOrder ? key($configOrder) : $query->getPrimaryKey());
        $orderType = $this->get('order-type', '', $configOrder ? current($configOrder) : 'desc');

        // 获取查询字段
        list($fields, $queryFields) = $this->fieldsConfig->frameworkGetListsFields($query->getPrimaryKey());
        $query->field($queryFields);

        //排序
        $order = [$orderField => $orderType];
        $query->order($order);

        // 自定义查询
        $this->join($query);
        $this->listsQuery($query);

        // 获取过滤器字段
        $filterFields = $this->fieldsConfig->frameworkGetFilterFields();

        // 接收过滤器的值，并且填充到页面的 输入框中
        $this->fieldsConfig->frameworkFilterRequest($query, $filterFields);

        // 克隆查询，查询总条数
        $countQuery = clone $query;
        $total = $countQuery->count();
        $totalPage = ceil($total / $querySize);

        // 查询出来数据
        $dbAll = $query->page($queryPage, $querySize)->selectAll();

        // 把 selectField 的列表查询提前，不然会导致 selectField 的每行一个查询
        Func::selectFieldBeforeQuery($this->fieldsConfig->fields, $dbAll);

        // 查找出来添加按钮旁边的操作
        $indexAddActions = $this->actionConfig->getActionByPos(PagePosEnum::INDEX_ADD);
        $firstAction = array_shift($indexAddActions);


        // 查找出来表格中的操作按钮
        $listActions = $this->actionConfig->getActionByPos(PagePosEnum::INDEX_LISTS);

        // 把查询出来的数据库字段转化成页面展示的字段
        $lists = [];
        foreach ($dbAll as $rowTable) {
            $lists[] = new ListRowManager($rowTable, $fields, $listActions);
        }

        $this->pageConfig->addJsFile('/admin/js/page-list.js');
        return TwigResponse::render('pages/lists.twig', [
            'firstAction' => $firstAction,
            'lastActions' => $indexAddActions,
            'pageConfig' => $this->pageConfig,
            'orderField' => $orderField,
            'orderType' => $orderType,
            'pageShowSize' => $this->pageConfig->pageShowSize,// 分页显示的最大条数
            'querySize' => $querySize,
            'filterFields' => $filterFields,
            'totalPage' => $totalPage,
            'page' => $queryPage,
            'lists' => $lists,
            'total' => $total,
            'listRefreshUrl' => $this->listRefreshUrl,
        ]);
    }


    /**
     * @throws Throwable
     */
    #[Router(method: ["GET", "POST"])]
    public function new(): TwigResponse|RedirectResponse
    {
        $this->Init(PagePosEnum::FORM_NEW);
        $method = $this->request->getMethod();
        list($fields) = $this->fieldsConfig->frameworkGetFormFields();
        if ($method === 'POST') {
            $table = $this->fieldsConfig->frameworkFormRequest($fields);
            $this->insertBefore($table);
            $this->insertUpdateBefore($table);
            $id = $table->insert($table->__toArray());
            $table->setPrimaryValue($id);
            $this->insertAfter($table);
            $this->insertUpdateAfter($table);
            return RedirectResponse::url(Func::url('lists'));
        } else {
            /** @var AbstractField[] $fields */
            foreach ($fields as $field) {
                if ($field->default) {
                    // 如果有默认值  设置字段的值
                    $field->value = $field->default;
                }
                if ($field->formCreate) {
                    call_user_func($field->formCreate, $field);
                }
            }

        }

        // 查找操作按钮
        $formNewActions = $this->actionConfig->getActionByPos(PagePosEnum::FORM_NEW);
        $firstAction = array_shift($formNewActions);

        $this->pageConfig->addJsFile('/admin/js/page-form.js');
        return TwigResponse::render('pages/form.twig', [
            'firstAction' => $firstAction,
            'lastActions' => $formNewActions,
            'pageConfig' => $this->pageConfig,
            'fields' => $fields,
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Router(method: ["GET", "POST"])]
    public function edit(): TwigResponse|RedirectResponse
    {
        $this->Init(PagePosEnum::FORM_EDIT);
        $table = new $this->pageConfig->tableName;
        $priName = $table->getPrimaryKey();
        $priValue = $this->get($table->getPrimaryKeyOriginal());

        list($fields, $queryFields) = $this->fieldsConfig->frameworkGetFormFields();

        // 查询数据
        $query = $table->field($queryFields)->addWhere($priName, $priValue);
        $this->join($query);
        $this->editQuery($query);

        $table = $query->selectOne();
        $method = $this->request->getMethod();

        if ($method === 'POST') {
            $table = $this->fieldsConfig->frameworkFormRequest($fields);
            $this->updateBefore($table);
            $this->insertUpdateBefore($table);
            $table->addWhere($priName, $priValue)->update($table->__toArray());
            $this->updateAfter($table);
            $this->insertUpdateAfter($table);
            $referer = $this->post("_referer", '', Func::url('lists', [], [$priName]));
            return RedirectResponse::url($referer);
        } else {
            if (empty($table)) {
                throw new AppException('参数错误');
            }
            $this->fieldsConfig->frameworkFormEditFill($fields, $table);
        }

        // 查找操作按钮
        $formEditActions = $this->actionConfig->getActionByPos(PagePosEnum::FORM_EDIT);
        $firstAction = array_shift($formEditActions);
        $this->pageConfig->addJsFile('/admin/js/page-form.js');
        return TwigResponse::render('pages/form.twig', [
            'firstAction' => $firstAction,
            'lastActions' => $formEditActions,
            'pageConfig' => $this->pageConfig,
            'fields' => $fields,
            'pri_value' => $priValue,
            'referer' => $this->request->header['referer']??'',
        ]);
    }


    /**
     * 删除一条数据
     * @throws Throwable
     */
    #[Router(method: "GET")]
    public function delete(): RedirectResponse
    {
        $this->Init(PagePosEnum::DELETE);
        $table = new $this->pageConfig->tableName;
        $priName = $table->getPrimaryKey();
        $priValue = $this->get($table->getPrimaryKeyOriginal());
        $table->setPrimaryValue($priValue);
        $this->deleteBefore($table);
        $table->addWhere($priName, $priValue)->delete();
        $this->deleteAfter($table);
        return RedirectResponse::url(Func::url('lists', [], [$priName]));
    }

    /**
     * @throws Throwable
     */
    #[Router(method: "GET")]
    public function detail(): TwigResponse
    {
        $this->Init(PagePosEnum::DETAIL);
        $table = new $this->pageConfig->tableName;
        $priName = $table->getPrimaryKey();
        $priValue = $this->get($table->getPrimaryKeyOriginal());

        list($fields, $queryFields) = $this->fieldsConfig->frameworkGetDetailFields($priName);
        $query = $table->addWhere($priName, $priValue)->field($queryFields);
        $this->join($query);
        $this->detailQuery($query);

        $rowTable = $query->selectOne();
        if (empty($rowTable)) {
            throw new AppException('参数错误');
        }

        // 查找出来表格中的操作按钮
        $detailActions = $this->actionConfig->getActionByPos(PagePosEnum::DETAIL);

        $rowManager = new ListRowManager($rowTable, $fields, $detailActions);

        return TwigResponse::render('pages/detail.twig', [
            'rowManager' => $rowManager,
            'pri_value' => $priValue,
            'pageConfig' => $this->pageConfig,
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Router(method: "POST")]
    public function switch(): JsonResponse
    {
        $this->Init(PagePosEnum::SWITCH);
        $field = $this->post('field');
        $value = $this->post('value');
        $priFieldName = $this->post('priFieldName');
        $priFieldValue = $this->post('priFieldValue');

        $table = new $this->pageConfig->tableName;
        $table->addWhere($priFieldName, $priFieldValue)->update([
            $field => $value
        ]);

        return JsonResponse::success();
    }

    /**
     * 获取关联列表，通过下拉输入框，输入的时候获取选择列表数据
     * @throws Throwable
     */
    #[Router(method: "POST")]
    public function getSelectList(): JsonResponse
    {
        $this->Init(PagePosEnum::GET_SELECT_LIST);
        $fieldName = $this->post('fieldName', "fieldName为空");
        $keyword = $this->post('keyword', '请输入关键字');
        $field = $this->fieldsConfig->frameworkGetField($fieldName);
        if (!($field instanceof SelectField)) {
            throw new AppException('参数错误');
        }


        if (empty($field->table)) {
            throw new AppException('查询未配置');
        }

        $table = new $field->table;
        $idField = $field->idField;
        $textField = $field->textField;
        $query = $table->field([$idField, $textField]);
        $query->addWhere($textField, "%$keyword%", 'like')->limit($this->pageConfig->querySize);

        if ($field->addQuery) {
            call_user_func($field->addQuery, $query);
        }

        $ret = [];
        foreach ($query->selectAll() as $list) {
            $id = $list->getByField($idField);
            $ret[] = [
                'id' => $id,
                'text' => $id . '#' . $list->getByField($textField)
            ];
        }


        return JsonResponse::success($ret);
    }


}