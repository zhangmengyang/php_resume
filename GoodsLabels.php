<?php


namespace app\model;

use app\controller\ApiAGVClient;
use app\model\ApiLog as ApiLogModel;
use app\model\Config as ConfigModel;
use app\model\GoodsBaseCate;
use app\model\GoodsLabels as GoodsLabelsModel;
use app\model\GoodsLabelsTemplate as GoodsLabelsTemplate;
use app\model\GoodsLabelsTemplate as GoodsLabelsTemplateModel;
use app\model\RecordIn as RecordInModel;
use app\model\Cmdline as CmdlineModel;
use app\model\RecordOut as RecordOutModel;
use app\model\RecordLabels;
use app\model\RecordProcess;
use app\model\RecordProcess as RecordProcessModel;
use app\model\RecordReceiving;
use app\model\RecordCmdline as RecordCmdlineModel;
use app\model\WorkIn as WorkInModel;
use app\model\DeviceConvey as DeviceConveyModel;
use app\model\TrayGoods as TrayGoodsModel;
use app\wcs\ConveyCMD;
use think\cache\driver\Redis;
use think\Model;
use think\validate\ValidateRule;


/**
 * 物料标签
 */
class GoodsLabels extends Model
{
    /**
     * 列表
     */
    public function getList($limit, $param)
    {
        $where = array();
        if (isset($param['numbers'])) $where[] = ['numbers', 'like', '%' . $param['numbers'] . '%'];
        if (isset($param['pono'])) $where[] = ['pono', 'like', $param['pono']];
        if (isset($param['goods_batch'])) $where[] = ['goods_batch', 'like', '%' . $param['goods_batch'] . '%'];
        if (isset($param['tray_numbers'])) $where[] = ['tray_numbers', '=', $param['tray_numbers']];
        if (isset($param['pdooutno'])) $where[] = ['pdooutno', '=', $param['pdooutno']];
        if (isset($param['record_type'])) $where[] = ['record_type', '=', $param['record_type']];
        if (isset($param['receivingid'])) $where[] = ['receivingid', '=', $param['receivingid']];
        if (isset($param['poitem'])) $where[] = ['poitem', 'like', '%' . $param['poitem'] . '%'];
        if (isset($param['goods_numbers'])) $where[] = ['goods_numbers', 'like', '%' . $param['goods_numbers'] . '%'];
        /*if(isset($param['processid']))        $where[]  = ['processid','like','%'.$param['processid'].'%'];*/

        //手持机查询-平库标签查询
        if (isset($param['start_tagvo']) && !empty($param['start_tagvo'])) { //接收起始标签号
            if (!isset($param['total']) || empty($param['total'])) {
                error(10020, '请输入标签总数');
            }
            /*if(!isset($param['processid']) || empty($param['processid'])){
                error(10020,'请选择业务流程');
            }*/
            $linshi = [];
            $linshi['pono'] = $param['pono'];
            $linshi['num_index'] = $param['start_tagvo'];
            $linshi['num_total'] = $param['total'];
            /*$linshi['processid']=$param['processid'];*/
            $startVo = $this->where($linshi)->find();
            unset($linshi);

            if (!$startVo) {
                error(10020, '无起始[' . $param['start_tagvo'] . ']号标签数据');
            }
            if (isset($param['end_tagvo'])) {
                if ($param['end_tagvo']) {
                    $linshi = [];
                    $linshi['pono'] = $param['pono'];
                    $linshi['num_index'] = $param['end_tagvo'];
                    $linshi['num_total'] = $param['total'];
                    /*$linshi['processid']=$param['processid'];*/
                    $endVo = $this->where($linshi)->find();
                    if (!$endVo) {
                        error(10020, '无结束[' . $param['end_tagvo'] . ']号标签数据');
                    }
                    unset($linshi);
                } else {
                    //不输入结束标签号，查找对应标签找到最后一个
                    $linshi = [];
                    $linshi['pono'] = $param['pono'];
                    $linshi['num_total'] = $param['total'];
                    /*$linshi['processid']=$param['processid'];*/
                    $endVo = $this->where($linshi)->order('id desc')->limit(1)->find();
                    unset($linshi);
                }
            }
            if ($startVo['id'] > $endVo['id']) {
                error(10028, '开始标签号不能大于结束标签号');
            } else {
                $where[] = ['id', '>=', $startVo['id']];
                $where[] = ['id', '<=', $endVo['id']];
                $where[] = ['pono', '=', $param['pono']];
                $where[] = ['num_total', '=', $param['total']];
                /*$where[]  = ['processid','=',$param['processid']];*/
            }
        }

        if (isset($param['end_tagvo']) && !empty($param['end_tagvo'])) {  //接收结束标签号
            if (!isset($param['total']) || empty($param['total'])) {
                error(10020, '请输入标签总数');
            }
            /*if(!isset($param['processid']) || empty($param['processid'])){
                error(10020,'请选择业务流程');
            }*/
            $linshi = [];
            $linshi['pono'] = $param['pono'];
            $linshi['num_index'] = $param['end_tagvo'];
            $linshi['num_total'] = $param['total'];
            /*$linshi['processid']=$param['processid'];*/
            $endVo = $this->where($linshi)->find();
            unset($linshi);
            if (!$endVo) {
                error(10020, '无结束[' . $param['end_tagvo'] . ']号标签数据');
            }

            if (empty($param['start_tagvo'])) {
                $linshi = [];
                $linshi['pono'] = $param['pono'];
                $linshi['num_index'] = 1;
                $linshi['num_total'] = $param['total'];
                /*$linshi['processid']=$param['processid'];*/
                $startVo = $this->where($linshi)->find();
                if (!$startVo) {
                    error(10020, '无起始[' . $param['start_tagvo'] . ']号标签数据');
                }
            }

            $where[] = ['id', '>=', $startVo['id']];
            $where[] = ['id', '<=', $endVo['id']];
            $where[] = ['pono', '=', $param['pono']];
            $where[] = ['num_total', '=', $param['total']];
            /*$where[]  = ['processid','=',$param['processid']];*/
        }


        // 手持机查询 END

        $list = $this->with(['labelstemplate', 'recordprocess', 'goodsbase', 'trayinfo'])
            ->where($where)
            ->order('id', 'desc')
            ->paginate($limit);
        foreach ($list as $key => $v) {
            $list[$key]['description'] = (new GoodsBase())->where('id', $v['goodsid'])->value('description');
            //$list[$key]['qcode_png'] = $this->qcode($v['id']);
        }
        if (!$list) error(10004);
        return $list;
    }

    /**
     * @param $id
     * 根据id返回标签集合
     */
    public function getIdOne($id, $field = '*')
    {
        $where = array();
        $where[] = ['id', '=', $id];
        return $this->field($field)->where($where)->find();
    }

    public function getLabelsList($limit, $param)
    {
        $where = array();
        $where[] = ['goods_pick_total', '>', 0];
        if (isset($param['numbers'])) $where[] = ['numbers', 'like', '%' . $param['numbers'] . '%'];
        if (isset($param['goods_batch'])) $where[] = ['goods_batch', 'like', '%' . $param['goods_batch'] . '%'];
        if (isset($param['pono'])) $where[] = ['pono', 'like', $param['pono']];
        if (isset($param['tray_numbers'])) $where[] = ['tray_numbers', '=', $param['tray_numbers']];
        if (isset($param['pdooutno'])) $where[] = ['pdooutno', '=', $param['pdooutno']];
        if (isset($param['record_type'])) $where[] = ['record_type', '=', $param['record_type']];
        if (isset($param['poitem'])) $where[] = ['poitem', 'like', '%' . $param['poitem'] . '%'];
        if (isset($param['goods_numbers'])) $where[] = ['goods_numbers', 'like', '%' . $param['goods_numbers'] . '%'];
        $list = $this->where($where)
            ->order('id', 'desc')
            ->paginate($limit);
        //var_dump($this->getLastSql());
        foreach ($list as $key => $v) {
            $list[$key]['description'] = (new GoodsBase())->where('id', $v['goodsid'])->value('description');
        }
        if (!$list) error(10004);
        return $list;
    }

    /**
     * 关联查询，标签打印模板
     */
    public function labelstemplate()
    {
        return $this->belongsTo(GoodsLabelsTemplate::class, 'templateid');
    }

    /**
     * 关联查询，业务流程
     */
    public function recordprocess()
    {
        return $this->belongsTo(RecordProcess::class, 'processid');
    }

    /**
     * 关联查询，基础物料
     */
    public function goodsbase()
    {
        return $this->belongsTo(GoodsBase::class, 'goodsid');
    }

    /**
     * 关联查询，载具
     */
    public function trayinfo()
    {
        return $this->belongsTo(Tray::class, 'trayid');
    }


    /**
     * 添加
     */
    public function add($param)
    {
        $count = $param['counts_labels'];
        unset($param['counts_labels']);
        $list = array();
        for ($i = 1; $i <= $count; $i++) {
            $param['num_total'] = $count;
            $param['num_index'] = $i;
            $param['qtstatus'] = 1;
            $param['create_time'] = time();
            $param['numbers'] = uniqid(rand(100000, 999999));
            $list[] = $this->insertGetId($param);
        }

        //$res = $this->insertAll($list);
        if (!$list) error(10003);
        return $list;
    }

    /**
     * 添加
     */
    public function addMes($param)
    {
        $goods_info = (new GoodsBase())->where('numbers', trim($param['materialno']))->find();
        if (!$goods_info) {
            error(10032, '未找到物料号');
        }
        $i = [];
        $i['workcenter'] = $param['equipmentno'] ?? '';//工作中心（机台号）
        $i['classes'] = $param['shiftno'] ?? '';//班次
        $i['pdoinno'] = $param['mono'] ?? '';//生产入库订单号  工单号
        $i['pono'] = $i['pdoinno'];//生产入库订单号  工单号
        $i['workers'] = $param['username'] ?? '';//作业员
        $i['labels_type'] = $param['barno'] ?? 0;//标签属性 1市场配件 2官方配件 3正常发货 4不合格品
        $i['record_type'] = $param['record_type'] ?? 1;//关联单据的类型 1-入库 2-出库 3-移库 4-盘库 5-拣选
        $i['templateid'] = $param['type'] ?? 2;//打印模板id
        $i['goodsid'] = $goods_info['id'];
        $i['goods_numbers'] = $goods_info['numbers'];//物料号
        $i['goods_title'] = $goods_info['title'];//物料名称
        $i['goods_description'] = $goods_info['description'];//物料规格
        $i['goods_batch'] = $param['lotno'] ?? '';//物料批次
        if (isset($param['supplier_batch'])) {
            $i['supplier_batch'] = $param['supplier_batch'] ?? '';//供应商批次
        }
        $i['goods_total'] = $param['qty'] ?? 0;//物料数量
        $i['qtstatus'] = $goods_info['qtstatus'] ?? 1;//质检状态 0-待质检  1-合格  2-不合格
        $i['ware_type'] = 0;
        $i['num_total'] = 0;
        $i['num_index'] = 0;
        $i['qtstatus'] = 1;
        $i['isposting'] = 1;//0未过账，MES创建的标签，都不用过账，因为mes单独过账
        $userid = $param['userid'] ?? 0;
        if ($userid < 1000) {
            $i['userid'] = $userid;//客户登陆的id，方便控制特定用户操作组盘，做个验证
        } else {
            $i['userid'] = 0;
        }
        if ($userid == 42) {
            $i['bomno'] = 'PadPrint';//手持机打印标签标识
        } else {
            $i['bomno'] = $param['bomno'] ?? 'MesPrint';//BOM备料单号 方便删除
        }
        $i['indate_now'] = time();
        $i['numbers'] = uniqid(rand(100000, 999999));
        $i['create_time'] = time();
        $rs = $this->insertGetId($i);
        return $rs;
    }

    /**
     * 复制
     */
    public function copy($param)
    {
        $res = $this->save($param);
        if (!$res) error(10003);
        return $res;
    }

    /**
     * 编辑
     */
    public function edit($param)
    {
        $print_id = $param['print_id'] ?? 0;//打印机id
        $id = $param['id'] ?? 0;
        unset($param['pono']);
        unset($param['print_id']);
        if (!$id) {
            error(10032, '编辑标签ID为空');
        }
        $param['create_time'] = time();
        unset($param['id']);
        $res = $this->where('id', $id)->update($param);
        if (!$res) error(10032, '更新失败');

        if ($print_id) {
            //将对应的标签库存也改下
            $res = (new TrayGoodsModel())->where('labelsid', $id)->update(['total' => $param['goods_total'], 'create_time' => time()]);
            $this->editPrintByLabelId(['id' => $id], $print_id);
        }
        return $res;
    }

    /**
     *通过托盘编号改变move_status状态  0-待移动 1-移动中  2-完成
     */
    public function setMoveStatusByTrayNo($TrayNo, $move_status = 0)
    {
        $res = $this->where('tray_numbers', $TrayNo)->save(['move_status' => $move_status]);
        //if(!$res) error(10009);
        return $res;
    }

    /**
     * 手持机针对单个标签退，进行单个处理 0-待移动 1-移动中  2-完成
     */
    public function setMoveStatusById($id, $move_status = 0, $record_type = 2)
    {
        $res = $this->where('id', $id)->save(['move_status' => $move_status, 'record_type' => $record_type, 'create_time' => time()]);
        return $res;
    }

    /**
     * 删除
     */
    public function del($id)
    {
        $idList = json_decode($id, true);
        $recordlabel = new RecordLabels();

        //删除关联单据与标签
        foreach ($idList as $item) {
            $recordlabel->delBylabelsid($item);
        }

        $res = $this->destroy($idList);
        if (!$res) error(10010);
        return $res;
    }

    /**
     * 四向车库组盘绑定
     * 立体库组盘绑定
     */
    public function binding($param)
    {
        // 启动事务
        \think\facade\Db::startTrans();
        try {
            $red = new Redis();
            if (!$param['tray_numbers']) {
                error(10032, '托盘号不能为空');
            }
            //直接解绑侯在组盘
            //$this->unbindingTrayNumbers(['tray_numbers'=>$param['tray_numbers']]);

            if (!$param['labels_numbers']) {
                error(10032, '标签号不能为空');
            }
            if (!$param['id']) {
                error(10032, '业务流程不能为空');
            }
            $equipment = $param['equipment'] ?? 0;//设备号
            $recordcmdline = new RecordCmdlineModel();

            $equipment2_type = substr($equipment, 0, 3);//获取设备号前3位

            if ($equipment2_type == 'M10') {
                $where = [];
                $where[] = ['isend', '=', 0];
                $where[] = ['cmdtype', '=', 1];
                $where[] = ['from_wcs', 'like', $equipment2_type . '%'];
                $isline_count = $recordcmdline->where($where)->count();
                if ($isline_count > 15) {
                    $replace = str_replace('M', '', $equipment2_type);
                    $replace = str_replace('0', '', $replace);
                    error(10032, $replace . '楼入超15托稍休息在组盘入库');
                }
            }

            $num_tray_numbers = substr($param['tray_numbers'], 0, 1);
            if ($num_tray_numbers == 3) {
                $dwhere = [];
                $dwhere[] = ['u', 'in', [3, 4]];
                $dwhere[] = ['ablestatus', 'in', [0, 2]];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                unset($dwhere);
                if (!$stopStacker) {
                    error(10032, '3/4无法组盘,只出不入');
                }

                $dwhere = [];
                $dwhere[] = ['u', 'in', [3, 4]];
                $dwhere[] = ['stopstatus', '=', 0];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                unset($dwhere);
                if (!$stopStacker) {
                    error(10032, '3/4被禁无法组盘');
                }
                $S4_big_count = $red->get('S3_big_count');
                $S3_big_count = $red->get('S3_big_count');
                if ($S4_big_count <= 0 && $S3_big_count <= 0) {
                    error(10032, '大托盘库已满无法入库');
                }

            } elseif ($num_tray_numbers == 2) {
                $dwhere = [];
                $dwhere[] = ['u', 'in', [1, 2, 5, 6]];
                $dwhere[] = ['ablestatus', 'in', [0, 2]];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                unset($dwhere);
                if (!$stopStacker) {
                    error(10032, '1/2/5/6无法组盘,只出不入');
                }
                $dwhere = [];
                $dwhere[] = ['u', 'in', [1, 2, 5, 6]];
                $dwhere[] = ['stopstatus', '=', 0];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                unset($dwhere);
                if (!$stopStacker) {
                    error(10032, '1/2/5/6被禁无法组盘');
                }
                $S1_count = $red->get('S1_count');
                $S2_count = $red->get('S2_count');
                $S5_count = $red->get('S5_count');
                $S6_count = $red->get('S6_count');
                if ($S1_count <= 0 && $S2_count <= 0 && $S5_count <= 0 && $S6_count <= 0) {
                    error(10032, '小托盘库已满无法入库');
                }
            } elseif ($num_tray_numbers == 1) {
                $SFourCarCount_count = $red->get('SFourCarCount_count');
                if ($SFourCarCount_count <= 0) {
                    error(10032, '四向库位保留6个空位置方便移库,已经放满了');
                }
                $getAvailableWareMove_count = (new Wareroom3d())->getAvailableWareMove_count();
                if ($getAvailableWareMove_count <= 6) {
                    error(10032, '四向库位预留可用库位数(6),目前可用库位:' . $getAvailableWareMove_count);
                }
            }
            //验证是2/3楼组盘验证是否堵线
            if (stripos('pos' . $equipment2_type, 'M20') || stripos('pos' . $equipment2_type, 'M30')) {
                if ($num_tray_numbers == 2) {
                    $dByU = (new DeviceConveyModel())->tiGaoFloorInFreeByU($equipment2_type);
                    unset($dByU[3]);
                    unset($dByU[4]);
                    if (empty($dByU)) {
                        $msg = [
                            '当前设备号' => $equipment,
                        ];
                        (new DeviceAlert())->setAlertMsg($msg, 2, $equipment . '小托盘堵线稍后组盘');
                        error(10032, '大托盘(1/2/5/6巷道)堵线稍后组盘');
                        return false;
                    }
                }
                if ($num_tray_numbers == 3) {
                    $dByU = (new DeviceConveyModel())->tiGaoFloorInFreeByU($equipment2_type);
                    unset($dByU[1]);
                    unset($dByU[2]);
                    unset($dByU[5]);
                    unset($dByU[6]);
                    if (empty($dByU)) {
                        //success_loger(20013,'tiGaoFloorInFreeByU'.__LINE__, $dByU);
                        $msg = [
                            '当前设备号' => $equipment,
                        ];
                        (new DeviceAlert())->setAlertMsg($msg, 2, $equipment . '大托盘堵线稍后组盘');
                        error(10032, '大托盘(3/4巷道)堵线稍后组盘');
                        return false;
                    }
                }
            }
            //载具状态判断结束
            $traymodel = new Tray();
            $trayinfo = $traymodel->where('numbers', $param['tray_numbers'])->find();
            if (!$trayinfo) error(10032, '无此托盘号');
            if ($trayinfo['pos'] > 0) error(10032, '托盘状态已在库~'); //status:载具状态 0-丢弃 1-可用 pos:载具位置 0-库外存放 1-输送中 2-已入库
            if ($trayinfo['goods'] == 1) { //绑定托盘时候托盘状态必须无货状态，无库存状态  goods:载具载物状态 0-无物料 1-有物料
                error(10032, '托盘有货状态');
            }

            $where = [];
            $where[] = ['traynumbers', '=', $trayinfo['numbers']];
            $where[] = ['status', '<>', 2];
            $TrayGoodsCount = (new CmdlineModel())->where($where)->count();
            if ($TrayGoodsCount) {
                error(10032, '输送线或者堆垛机有未完成的任务~');
            }
            unset($where);
            //查询标签信息
            $labelsinfo = $this->where('numbers', '=', $param['labels_numbers'])->find();
            if (!$labelsinfo) error(10032, '标签未能找到');
            if ($labelsinfo['trayid'] > 0) {
                error(10032, '该标签已经绑定托盘');
            }
            //更新标签入口设备号
            $this->where('id', '=', $labelsinfo['id'])->save(['start_postion' => $equipment]);


            $traygoods = new TrayGoodsModel();
            //验证是否已经组盘
            $where = [];
            $where[] = ['trayid', '=', $trayinfo['id']];
            $where[] = ['wareid', '=', 0];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if ($TrayGoodsCount) {
                error(10032, '托盘已经组盘');
            }
            unset($where);

            //验证是否已经组盘
            $where = [];
            $where[] = ['trayid', '=', $trayinfo['id']];
            $where[] = ['wareid', '>', 0];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if ($TrayGoodsCount) {
                error(10032, '托盘已在库内');
            }
            unset($where);

            //验证是否托盘号意在库内
            $where = [];
            $where[] = ['trayid', '=', $trayinfo['id']];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if ($TrayGoodsCount) {
                error(10032, '已经存在组盘信息,解绑重新组盘');
            }
            unset($where);


            if (strtoupper($equipment2_type) == 'M10') {
                if ($param['id'] != 69) {//如果是1楼入，只能选择M1007业务流程
                    error(10032, '您选择的业务流程不是立体库1楼入M1007');
                }
            }
            if (strtoupper($equipment2_type) == 'M40') {
                if ($param['id'] != 77) {//如果是1楼入，只能选择M1007业务流程
                    error(10032, '您选择的业务流程不是立体库4楼入M4009');
                }
            }


            //验证标签业务流程与
            /*if($labelsinfo['processid'] != $param['id']){
                error(10032,'标签业务流程与实际入库业务流程错误~');
            }*/
            //业务流程
            $recordprocess = new RecordProcess();

            //二楼或者三楼围板箱入
            if (strtoupper($equipment2_type) == 'M20' || strtoupper($equipment2_type) == 'M30') {
                $RecordProcessNumbers = $recordprocess->getFloorInNumbers($equipment);//根据设备号获取对应的业务流程编号
                $processid = $recordprocess->getNumbersById($RecordProcessNumbers);//拣选目标位入库
                $proessinfo = $recordprocess->find($processid);
                if (!$proessinfo) {
                    error(10032, '无业务流程数据' . $equipment);
                }

            } else {
                if (strtoupper($equipment2_type) == 'M10') {//1楼入
                    $proessinfo = $recordprocess->find(69);
                    if (!$proessinfo) error(10032, '无业务流程数据');
                } elseif (strtoupper($equipment2_type) == 'M40') {//4楼入
                    $proessinfo = $recordprocess->find(77);
                    if (!$proessinfo) error(10032, '无业务流程数据');
                } else {
                    $proessinfo = $recordprocess->find($param['id']);
                    if (!$proessinfo) error(10032, '无业务流程数据');
                }

            }

            //判断与输送线心跳断开不在运行下任务-----------start-----------{{如果没有找到该字符串，则返回 false
            /*if(strpos($proessinfo['title'],'四向') === false){
                $checkConveyHeartBeat = (new RecordOutModel())->checkConveyHeartBeat($equipment);
                if($checkConveyHeartBeat){
                    error(10032,'WCS与设备WCS与设备'.$equipment.'通信断开,暂时无法下任务');
                }
            }*/
            //判断与输送线心跳断开不在运行下任务-----------end-----------}}

            //四向车库
            /* if(in_array($proessinfo['numbers'],[110,212,1047,1040])){
                if($labelsinfo['processid'] != $proessinfo['id']) error(10009,'当前标签流程与实际组盘流程不符');
            }*/

            $config = \think\facade\Config::get('config');
            $receivingid = 0;
            //通过移动类型确认是哪种类型的业务，并查询对应的主数据表, 收货与质检
            if ($proessinfo['numbers'] == '110') {
                if ($param['id'] != 1) {
                    error(10032, '采购收货四向车业务流程错误~');
                }
                if (in_array($labelsinfo['userid'], $config['privilegeUserId'])) {//徐东可以绕开ERP直接组盘
                    $out_numbers = time() . rand(1000, 9999);
                    $numbers = $out_numbers;
                    $rows = 1;
                    $total = $labelsinfo['goods_total'];
                    $receivingid = 0;
                } else {
                    //采购和委外
                    $recing = new RecordReceiving();
                    $oneinfo = $recing->getReceiving($labelsinfo['receivingid']);
                    if (!$oneinfo) error(10032, '无质检数据');//如果没有查到对应表数据，直接跳出
                    $numbers = $oneinfo['rec_numbers'];//收货单号
                    if (!$numbers) {
                        error(10032, '没货到到ERP收货单号');
                    }
                    $out_numbers = $oneinfo['out_numbers'];
                    $rows = $oneinfo['out_rows'];
                    $total = $oneinfo['total'];
                    $receivingid = $oneinfo['id'];//wx_record_receiving表主键id

                }
                $processid = $proessinfo['id'];
                $needposting = 2;
            } elseif ($proessinfo['numbers'] == '212') {//原材料退料入四向库
                if (in_array($labelsinfo['userid'], $config['privilegeUserId'])) {//徐东可以绕开ERP直接组盘
                    $out_numbers = time() . rand(1000, 9999);
                    $numbers = $out_numbers;
                    $rows = 1;
                    $total = $labelsinfo['goods_total'];
                    $receivingid = 0;
                } else {
                    //采购和委外
                    $WorkInModel = new WorkInModel();
                    $oneinfo = $WorkInModel->getDataFind($labelsinfo['receivingid']);
                    if (!$oneinfo) error(10032, '无原材料退料入库数据');//如果没有查到对应表数据，直接跳出
                    $numbers = $oneinfo['out_numbers'];
                    $out_numbers = $oneinfo['out_numbers'];
                    $rows = $oneinfo['out_rows'];
                    $total = $oneinfo['total'];
                    $receivingid = $oneinfo['id'];//wx_work_in表主键id
                }
                $processid = $proessinfo['id'];
                $needposting = 2;//0-无需过账  1-逐笔过账  2-整单过账
            } elseif (in_array($proessinfo['numbers'], [1047, 1040])) {// 立体库1楼M1007入，4楼M1009入  1047:四楼立库成品入库    1040:1楼成品入库

                if (!in_array($param['id'], [69, 77])) {
                    error(10032, '立体库业务流程错误~');
                }

                $wareroom3dmodel = new Wareroom3d();
                $traynumbers = $trayinfo['numbers'];//托盘编号

                $checknum = $wareroom3dmodel->checkWareroom3dByU($traynumbers);
                if (!$checknum) {
                    error(10032, '仓位已满，不能进行组盘操作');
                }
                //采购和委外
                $recing = new RecordReceiving();
                $oneinfo = $recing->getReceiving($labelsinfo['receivingid']);
                if (!$oneinfo) {//无收货单数据
                    $out_numbers = time() . rand(1000, 9999);
                    $numbers = $out_numbers;
                    $rows = 1;
                    $total = $labelsinfo['goods_total'];
                    $receivingid = 0;//wx_record_receiving表主键id
                    $isposting = 0;//是否完成过账   0-未过账  1-已过账
                    $needposting = 0;//0-无需过账  1-逐笔过账  2-整单过账
                } else {
                    $out_numbers = $oneinfo['out_numbers'];
                    $numbers = $oneinfo['rec_numbers'];//收货单号
                    $rows = $oneinfo['out_rows'];
                    $total = $oneinfo['total'];
                    $receivingid = $oneinfo['id'];//wx_record_receiving表主键id
                    $isposting = 0;//是否完成过账   0-未过账  1-已过账
                    $needposting = 2;//0-无需过账  1-逐笔过账  2-整单过账
                }
                $processid = $proessinfo['id'];
            } elseif (in_array($proessinfo['numbers'], [1000, 1003, 1004, 1005, 1007, 1008, 1010, 1011, 1014, 2003, 2004, 2005, 2006, 2007, 2008, 2009, 2075, 2074, 2073, 1038, 1036, 1035, 1033, 1032, 1030, 1026, 1024, 1022, 1021, 1020, 1015, 'M3001'])) {//立体库二楼/三楼入库

                unset($where);
                $wareroom3dmodel = new Wareroom3d();
                $traynumbers = $trayinfo['numbers'];//托盘编号
                $checknum = $wareroom3dmodel->checkWareroom3dByU($traynumbers);
                if (!$checknum) {
                    error(10032, '仓位已满，不能进行组盘操作');
                }
                //采购和委外
                $recing = new RecordReceiving();
                $oneinfo = $recing->getReceiving($labelsinfo['receivingid']);
                if (!$oneinfo) {
                    $out_numbers = time() . rand(1000, 9999);
                    $numbers = $out_numbers;
                    $rows = 1;
                    $total = $labelsinfo['goods_total'];
                    $receivingid = 0;//wx_record_receiving表主键id
                } else {
                    $out_numbers = $oneinfo['out_numbers'];
                    $numbers = $oneinfo['rec_numbers'];//收货单号
                    $rows = $oneinfo['out_rows'];
                    $total = $oneinfo['total'];
                    $receivingid = $oneinfo['id'];//wx_record_receiving表主键id
                }
                $processid = $proessinfo['id'];
                $needposting = 0;//0-无需过账  1-逐笔过账  2-整单过账
                //验证是否是2楼入，要进行写入PLC托盘码-----start--------------{{
                $where = [];
                $where[] = ['keyword', '=', $proessinfo['from_wms']];
                $config_rows = (new Config())->where($where)->find();
                $value = substr($config_rows['value'], 0, 3);
                if ($value == 'M20' || $value == 'M30') {//对应二楼或者三楼
                    $where = array();
                    $where['title'] = $config_rows['value'];
                    $devconvey = new DeviceConvey();
                    $conveyinfo = $devconvey->where($where)->find();
                    if (!$conveyinfo) {
                        error(10032, '无设备名称');
                    }
                    $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                    $conCMD->setFloorTrayNo($conveyinfo['title'], $traynumbers);
                }
                //验证是否是2楼入，要进行写入托盘码-----start--------------}}

            } else {
                error(10032, '业务流程code码不存在');
            }

            //验证是否托盘号意在库内
            /*$where=[];
            $where[] = ['trayid','=',$trayinfo['id']];
            $where[] = ['wareid','>',0];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if($TrayGoodsCount){
                error(10032,'托盘已在库内无法组盘,请查下是否托盘号重复~');
            }
            unset($where);*/


            //组盘任务

            $where = [];
            $where[] = ['tray_numbers', '=', $trayinfo['numbers']];
            $where[] = ['isend', '=', 0];
            $where[] = ['isactivated', '=', 1];//已激活状态
            $is_cmd_line_count = $recordcmdline->where($where)->count();
            unset($where);
            if ($is_cmd_line_count) {
                error(10032, '有任务未完成');
            }
            //查询入库单据是否有组盘的信息
            $recordIn = new RecordIn();
            $where = [];
            $where[] = ['processid', '=', $processid];//流程id
            $where[] = ['numbers', '=', $numbers];//单据编号
            $where[] = ['rows', '=', $rows];//项次或序号，多次收货区分，外部项次可关联到此
            $where[] = ['goodsid', '=', $labelsinfo['goodsid']];//关联物料ID
            $recordIninfo = $recordIn->where($where)->find();
            //error(10009,'====processid='.$recordIn->getLastSql());
            //检查是否有相同的物料数据，如果没有新增上架单据一行物料，如果有只做数量追加
            if ($recordIninfo) {
                //if($recordIninfo['num_bind']+$labelsinfo['goods_total']>$total) error(10009,'组盘数量超过计划入库数量');
                $data = [];
                //追加组盘数量
                $data['num_bind'] = $recordIninfo['num_bind'] + $labelsinfo['goods_total'];
                //判断这个收货单是不是生成一条单据
                //$rc_count = ( new RecordReceiving())->where('rec_numbers',$numbers)->count();
                /*if(stripos('pos'.$numbers,'SZHT-PSH')){//是单独收一个直接过账
                    $data['num_plan'] = $data['num_bind'];
                    $data['num_now'] = $data['num_bind'];
                }*/
                $data['labelsid'] = $labelsinfo['id'];
                $recordIn->where($where)->save($data);
                unset($where);
                $resid = $recordIninfo['id'];
            } else {
                $data = [];
                //if($labelsinfo['goods_total']>$total) error(10009,'组盘数量超过计划入库数量');
                //新增入库单据
                /*$data['processid'] = $proessinfo['processid'];
                $data['wareroom'] = $proessinfo['wareroom'];*/
                $data['numbers'] = $numbers;
                $data['out_numbers'] = $out_numbers;
                $data['receivingid'] = $receivingid;
                $data['processid'] = $processid;
                $data['wareroom'] = $proessinfo['wareroom'];
                $data['rows'] = $rows;//$oneinfo['out_rows'];
                $data['labelsid'] = $labelsinfo['id'];//标签ID
                $data['goods_numbers'] = $labelsinfo['goods_numbers'];
                $data['goods_batch'] = $labelsinfo['goods_batch'];
                $data['goods_title'] = $labelsinfo['goods_title'];
                $data['goodsid'] = $labelsinfo['goodsid'];
                $data['num_plan'] = $total;
                $data['isposting'] = $isposting ?? 0;
                $data['needposting'] = $needposting ?? 0;
                $data['num_bind'] = $labelsinfo['goods_total'];
                $data['create_time'] = time();
                $resid = $recordIn->insertGetId($data);
                if (!$resid) {
                    error(10032, '生成入库单据失败~');
                }
                //$recordIninfo = $recordIn->where(['id','=',$resid])->find();
            }
            //error(10032,$processid.'=$processid   $resid='.$resid);
            //标签绑定托盘
            $d1 = [];
            $d1['tray_numbers'] = $trayinfo['numbers'];
            $d1['trayid'] = $trayinfo['id'];
            $d1['sdvono'] = $numbers;
            $d1['recordid'] = $resid;
            $d1['record_type'] = $proessinfo['record_type'];
            if ($labelsinfo['bomno'] == 'mesPrintAddMes') {
                $d1['bomno'] = 'ManualPrinting';//修改成立体库人工打印标签
            }
            if ($labelsinfo['processid'] == 0) {
                $d1['processid'] = $processid;
            }
            $resinfo = $this->where('id', $labelsinfo['id'])->save($d1);   //通标签id，绑定托盘
            unset($d1);
            if (!$resinfo) {
                error(10032, '标签绑托盘失败');
            }

            $d1 = [];
            $d1['goods'] = 1;
            $d1['code'] = '';
            $d1['create_time'] = time();
            (new Tray())->where('id', $trayinfo['id'])->save($d1);    //标记托盘号有货物
            unset($d1);
            //入库单据与物料标签关联
            $recordlabel = new RecordLabels();
            $d2 = [];
            $d2['recordid'] = $resid;
            $d2['labelsid'] = $labelsinfo['id'];
            $d2['record_type'] = $proessinfo['record_type'];
            $recordlabel->add($d2);
            unset($d2);
            //预写库存信息
            $traygoods = new TrayGoods();
            $data2 = [];
            $data2['type'] = $proessinfo['wareroom'] ?? 0;
            $data2['labelsid'] = $labelsinfo['id'];
            $data2['trayid'] = $trayinfo['id'];
            $data2['goodsid'] = $labelsinfo['goodsid'];
            $data2['total'] = $labelsinfo['goods_total'];
            $goods_sucees = $traygoods->save($data2);
            unset($data2);

            //更新上一次拣选状态
            $sql_where = array();
            $sql_where[] = ['tray_numbers', '=', $trayinfo['numbers']];
            $sql_where[] = ['record_type', '=', 5];
            $sql_where[] = ['ispick', '=', 0];
            $sql_where[] = ['isend', '=', 1];
            $recordcmdline->where($sql_where)->update(['ispick' => 1]);
            unset($sql_where);
            $cmdtype = 1;
            if ($proessinfo['record_type'] == 6) {//4楼上组装线 走提升机下一楼入库
                $cmdtype = $proessinfo['record_type'];
            }
            //生成任务队列
            $sucees = $recordcmdline->addRecordInCmdline($trayinfo['numbers'], $resid, $proessinfo['record_type'], $cmdtype);
            if (!$sucees) {
                error(10032, '任务失败');
            }
            if ($equipment) {
                $equipment_type = substr($equipment, 0, 1);
                if (strtoupper($equipment_type) != 'M') {
                    error(10032, '设备错误' . $equipment);
                }
                $where = array();
                $where['title'] = $equipment;
                $devconvey = new DeviceConvey();
                $conveyinfo = $devconvey->where($where)->find();
                unset($where);
                if (!$conveyinfo) {
                    error(10032, '无设备名称' . $equipment);
                }
                $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                $conCMD->sendConfirmIn($conveyinfo['title']);
            }
            if (in_array($equipment2_type, ['M20', 'M30'])) {//2楼或者3楼入单独给电气写入18状态
                /*$equipment_type = substr($equipment,0,1);
                if(strtoupper($equipment_type) != 'M'){
                    error(10009,'设备错误'.$equipment);
                }*/
                $where = array();
                $where['title'] = $equipment;
                $devconvey = new DeviceConvey();
                $conveyinfo = $devconvey->where($where)->find();
                unset($where);
                if (!$conveyinfo) {
                    error(10032, '无设备名称' . $conveyinfo['title']);
                }
                $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                $conCMD->setFloorTrayNo($conveyinfo['title'], $trayinfo['numbers']);
                //获取工位
                $rows_pick = (new DevicePick())->getDevicePick($equipment);
                if ($rows_pick) {
                    $sql_where = array();
                    $sql_where[] = ['stationid', '=', $rows_pick['id']];
                    $sql_where[] = ['record_type', '=', 5];
                    $sql_where[] = ['to_wcs', '=', $rows_pick['convey_title1']];
                    //$sql_where[] = ['isend','=',0];
                    $pickId = $recordcmdline->where($sql_where)->order('id', 'desc')->value('recordid');
                    unset($sql_where);
                    if ($pickId) {
                        $RecordPickInfo = (new RecordPick())->where('id', $pickId)->find();
                        if ($RecordPickInfo['rows'] > 0) {//继续出空围板箱子
                            $pickArrTitle = [
                                'M2016',
                                'M2013',
                                'M2010',
                                'M2007',
                                'M2004',
                                'M3016',
                                'M3013',
                                'M3010',
                                'M3007',
                                'M3004'
                            ];
                            if (in_array($equipment, $pickArrTitle)) {//源拣选位置
                                //业务流程
                                //$RecordProcessModel = new RecordProcess();
                                $RecordProcessNumbers = $recordprocess->getFloorOutNumbers($equipment);//根据设备号获取对应的业务流程编号
                                $process_id = $recordprocess->getNumbersById($RecordProcessNumbers);//拣选目标位入库
                                (new RecordPick())->addPickingPositionContinue([
                                    'goodsid' => $RecordPickInfo['goodsid'],
                                    'stationid' => $rows_pick['id'],
                                    'pickId' => $pickId,
                                    'process_id' => $process_id
                                ]);
                            } else {//目标拣选位置
                                $post = [];
                                $post['goodsid'] = $RecordPickInfo['goodsid'];
                                $post['pickId'] = $RecordPickInfo['id'];
                                $sucees = (new RecordPick())->addTargetPicking([$equipment], $post);
                                /*if(!$sucees){
                                    error(10032,$equipment.'出箱子失败~');
                                }*/
                            }
                        }
                    }


                }

            }
            \think\facade\Db::commit();
            return $goods_sucees;
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            error(10032, $e->getMessage());
        }

    }

    /**
     * 围板箱子
     * 立体库组盘绑定
     */
    public function bindingBox($param)
    {
        // 启动事务
        \think\facade\Db::startTrans();
        try {
            if (!$param['tray_numbers']) {
                error(10032, '托盘号不能为空');
            }
            if (!$param['box_code']) {
                error(10032, 'box_code不能为空');
            }
            if (!$param['id']) {
                error(10032, '业务流程不能为空');
            }
            $equipment = $param['equipment'] ?? 0;//设备号
            $equipment2_type = substr($equipment, 0, 3);//获取设备号前3位

            $num_tray_numbers = substr($param['tray_numbers'], 0, 1);
            if ($num_tray_numbers == 3) {
                $dwhere = [];
                $dwhere[] = ['u', 'in', [3, 4]];
                $dwhere[] = ['stopstatus', '=', 0];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                if (!$stopStacker) {
                    error(10032, '大托盘库全部被禁用!无法组盘');
                }
            } elseif ($num_tray_numbers == 2) {
                $dwhere = [];
                $dwhere[] = ['u', 'in', [1, 2, 5, 6]];
                $dwhere[] = ['stopstatus', '=', 0];
                $stopStacker = (new DeviceStacker())->where($dwhere)->count();
                if (!$stopStacker) {
                    error(10032, '小托盘库全部被禁用!无法组盘');
                }
            }
            //查询载具信息
            $traymodel = new Tray();
            $trayinfo = $traymodel->where('numbers', $param['tray_numbers'])->find();
            if (!$trayinfo) error(10032, '无托盘');
            if ($trayinfo['pos'] > 0) error(10029, '托盘状态在库内检查出库任务是否完成'); //status:载具状态 0-丢弃 1-可用 pos:载具位置 0-库外存放 1-输送中 2-已入库


            $traygoods = new TrayGoodsModel();
            $where = [];
            $where[] = ['trayid', '=', $trayinfo['id']];
            //$where[] = ['wareid','=',0];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if ($TrayGoodsCount) {
                error(10029, '此托盘已经组盘了');
            }
            $where = [];
            $where[] = ['trayid', '=', $trayinfo['id']];
            $where[] = ['wareid', '>', 0];
            $TrayGoodsCount = $traygoods->where($where)->count();
            if ($TrayGoodsCount) {
                error(10029, '此托盘已在库内无法组盘');
            }
            //业务流程
            $RecordProcessModel = new RecordProcess();
            //二楼或者三楼围板箱入
            if (strtoupper($equipment2_type) == 'M20' || strtoupper($equipment2_type) == 'M30') {
                $RecordProcessNumbers = $RecordProcessModel->getFloorInNumbers($equipment);//根据设备号获取对应的业务流程编号
                $processid = $RecordProcessModel->getNumbersById($RecordProcessNumbers);//拣选目标位入库
                $proessinfo = $RecordProcessModel->find($processid);
                if (!$proessinfo) error(10032, '无业务流程数据');
            } else {
                //$proessinfo = $RecordProcessModel->find($labelsinfo['processid']);
                $proessinfo = $RecordProcessModel->find($param['id']);
                if (!$proessinfo) error(10032, '无业务流程数据');
            }

            //组盘任务
            $recordcmdline = new RecordCmdlineModel();
            $where = [];
            $where[] = ['tray_numbers', '=', $trayinfo['numbers']];
            $where[] = ['isend', '=', 0];
            //$where[] = ['isactivated','=',1];//已激活状态
            $is_cmd_line_count = $recordcmdline->where($where)->count();
            if ($is_cmd_line_count) {
                error(10032, '此任务未完成');
            }
            $d1 = [];
            $d1['goods'] = 1;
            $d1['code'] = $param['box_code'];
            $config = \think\facade\Config::get('config');
            if (in_array($param['box_code'], $config['IsCage'])) {
                $d1['is_cage'] = 1;//0专用箱子，1=仓储笼
            }
            (new Tray())->where('id', $trayinfo['id'])->save($d1);    //标记托盘号有货物
            //预写库存信息
            $data2 = [];
            $data2['type'] = $proessinfo['wareroom'] ?? 0;
            $data2['trayid'] = $trayinfo['id'];
            $goods_sucees = $traygoods->save($data2);

            $sql_where = array();
            $sql_where[] = ['tray_numbers', '=', $trayinfo['numbers']];
            $sql_where[] = ['record_type', '=', 5];
            $sql_where[] = ['ispick', '=', 0];
            $sql_where[] = ['isend', '=', 1];
            $recordcmdline->where($sql_where)->update(['ispick' => 1]);
            //生成任务队列
            $sucees = $recordcmdline->addbindingBoxCmdline($trayinfo['numbers'], $proessinfo['id'], 1, 1);
            if (!$sucees) {
                error(10032, '生成入库任务失败');
            }
            if (in_array($equipment2_type, ['M10', 'M40'])) {//1楼或者四楼入，直接入库确认即可
                $equipment_type = substr($equipment, 0, 1);
                if (strtoupper($equipment_type) != 'M') {
                    error(10032, '设备错误' . $equipment);
                }
                $where = array();
                $where['title'] = $equipment;
                $devconvey = new DeviceConvey();
                $conveyinfo = $devconvey->where($where)->find();
                if (!$conveyinfo) {
                    error(10020, '无设备名称' . $equipment);
                }
                $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                $conCMD->sendConfirmIn($conveyinfo['title']);
            }
            if (in_array($equipment2_type, ['M20', 'M30'])) {//2楼或者3楼入单独给电气写入18状态
                $equipment_type = substr($equipment, 0, 1);
                if (strtoupper($equipment_type) != 'M') {
                    error(10032, '设备错误' . $equipment);
                }
                $where = array();
                $where['title'] = $equipment;
                $devconvey = new DeviceConvey();
                $conveyinfo = $devconvey->where($where)->find();
                if (!$conveyinfo) {
                    error(10020, '无设备名称');
                }
                $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                $conCMD->setFloorTrayNo($conveyinfo['title'], $trayinfo['numbers']);
            }
            \think\facade\Db::commit();
            return $goods_sucees;
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            error(10032, $e->getMessage());
        }

    }

    /**
     * 扫标签对应的需要解绑的托盘号
     * @param $param
     */
    public function unbindingByTrayNumbers($param)
    {
        if (!isset($param['labels_numbers'])) {
            error(10020, '收到：' . $param['labels_numbers']);
        }
        $labelsinfo = $this->where('numbers', '=', $param['labels_numbers'])->find();
        //检查任务队列是否已经启动
        /*if($labelsinfo['tray_numbers']){
            $cmdline = new Cmdline();
            $where[] = ['status','=',1];
            $where[] = ['traynumbers','=',$labelsinfo['tray_numbers']];
            $cmd = $cmdline->where($where)->count();
            unset($where);
            if($cmd) error(10020,'子任务在执行中,如需删除需要电控配合!');
        }*/
        //0-待移动 1-移动中  2-完成,标签状态归位
        $this->where('numbers', '=', $param['labels_numbers'])->save(['move_status' => 0]);
        if (!$labelsinfo) error(10020, '标签未能找到,标签号:' . $param['labels_numbers']);
        return $labelsinfo;
    }

    /**
     * 标签解除绑定
     */
    public function unbinding($param)
    {
        if (!isset($param['labels_numbers'])) {
            error(10032, '未收标签号');
        }
        $labelsinfo = $this->field('id,recordid,goods_total,trayid,tray_numbers,tray_numbers,move_status')->where('numbers', '=', $param['labels_numbers'])->find()->toArray();
        //if(!$labelsinfo) error(10020,'标签未能找到');
        $tray_numbers = $labelsinfo['tray_numbers'];
        if ($labelsinfo['move_status'] > 0) error(10032, '标签状态必须为0，才能解除绑定');
        //组盘任务
        $recordcmdline = new RecordCmdlineModel();


        $where = [];
        $where[] = ['tray_numbers', '=', $tray_numbers];
        $where[] = ['cmdtype', '=', 0];
        $where[] = ['isend', '=', 0];
        $is_cmd_line_count = $recordcmdline->where($where)->count();
        unset($where);
        if ($is_cmd_line_count == 0) {//如果没有正在出库执行中任务的话，要进行验证
            $where = [];
            $where[] = ['labelsid', '=', $labelsinfo['id']];
            $where[] = ['wareid', '>', 0];
            $where[] = ['trayid', '>', 0];
            $lcount = (new TrayGoods())->where($where)->count();
            unset($where);
            if ($lcount) {
                $t_numbers = (new TrayGoods())->getLablesIdByNumbers($labelsinfo['id']);
                if ($t_numbers) {
                    error(10032, '此标签已经绑定(' . $t_numbers . ')托盘在库内,无法解绑');
                }
                error(10032, '此标签绑定托盘号系统内未找到');
            }
            //error(10032,'当前有出口任务未完成,请手动标记完成即可~');
        }

        //检查任务队列是否已经启动
        /*$where[] = ['tray_numbers','=',$labelsinfo['tray_numbers']];
        $where[] = ['isactivated','=',0];
        $where[] = ['isend','=',0];
        $redcdline = $recordcmdline->where($where)->find();
        unset($where);
        if(!isset($redcdline)) error(10020,'任务队列中没有符合要求的托盘，可能已经入库');*/
        if ($tray_numbers) {
            //将对应的AGV点位上的托盘号给解绑掉
            $where = array();
            $where[] = ['tray_numbers', '=', $tray_numbers];
            $tcount = (new DeviceAgvpos())->where($where)->count();
            if ($tcount) {

                $where1 = array();
                $where1[] = ['devicetype', '=', 2];
                $where1[] = ['traynumbers', '=', $tray_numbers];
                $where1[] = ['status', '<>', 2];
                $taskno = (new CmdlineModel())->where($where1)->value('taskno');
                if ($taskno) {
                    (new DeviceAgvpos())->AgvResetPost($taskno);
                }

                $res = (new DeviceAgvpos())->where($where)->save([
                    'tray_numbers' => 0,
                    'islock' => 0,
                    'is_call' => 0,
                    'create_time' => time()
                ]);
            }

            $cmdline = new Cmdline();
            $where = [];
            $where[] = ['traynumbers', '=', $tray_numbers];
            $where[] = ['status', '<>', 2];//已经完成的不在删除
            $cmd = $cmdline->where($where)->delete();
            unset($where);
            $where = [];
            //删除任务队列
            $where[] = ['tray_numbers', '=', $tray_numbers];
            $where[] = ['isend', '=', 0];
            //删除主任务时候要将锁定状态变更可用
            $wareid = $recordcmdline->where($where)->value('wareid');

            if ($wareid) {
                (new Wareroom3d())->where('id', $wareid)->save(['status' => 0]);
            }
            $cmdtype = $recordcmdline->where($where)->value('cmdtype');
            $from_wcs = $recordcmdline->where($where)->value('from_wcs');
            unset($where);
            if ($cmdtype == 1 && $from_wcs) {
                $equipment2_type = substr($from_wcs, 0, 3);//获取设备号前3位
                //删除任务时候，取消任务线入库任务
                if (strtoupper($equipment2_type) == 'M20' || strtoupper($equipment2_type) == 'M30') {
                    $where4 = array();
                    $where4['title'] = $from_wcs;
                    $devconvey = new DeviceConvey();
                    $conveyinfo = $devconvey->where($where4)->find();
                    unset($where4);
                    if (!$conveyinfo) {
                        error(10032, '无设备名称' . $from_wcs);
                    }
                    $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                    $conCMD->resetWcsInSend($conveyinfo['title']);
                }
            }
            $where = [];
            //删除任务队列
            $where[] = ['tray_numbers', '=', $tray_numbers];
            $where[] = ['isend', '=', 0];
            $recordcmdline->where($where)->delete();
            unset($where);

        }

        //删除预写库存信息
        $traygoods = new TrayGoods();
        $type = $traygoods->where('labelsid', $labelsinfo['id'])->value('type');//根据标签id验证是否是平库标签
        if ($type == 1) {
            error(10032, '平库不能通过此处解绑');
        }
        $where = [];
        $where[] = ['labelsid', '=', $labelsinfo['id']];
        $where[] = ['trayid', '=', $labelsinfo['trayid']];
        $tg_wareid = $traygoods->where($where)->value('wareid');
        if (intval($tg_wareid) > 0) {
            $success = (new Wareroom3d())->where('id', $tg_wareid)->update(['status' => 0, 'goods_status' => 0, 'create_time' => time()]);
            if (!$success) {
                error(10032, '解绑更新库位状态失败');
            }
        }
        $success = $traygoods->where($where)->delete();
        /*if(!$success){
            error(10020,'解绑删除库存失败');
        }*/
        unset($where);


        //清除标签绑定信息
        $res = $this->where('id', $labelsinfo['id'])->save(['trayid' => 0, 'recordid' => 0, 'tray_numbers' => 0]);

        //清除关联表中的信息
        $recordlabel = new RecordLabels();
        $where = [];
        $where[] = ['labelsid', '=', $labelsinfo['id']];
        $where[] = ['recordid', '=', $labelsinfo['recordid']];
        $recordlabel->where($where)->delete();
        unset($where);
        $d1 = [];
        $d1['goods'] = 0;
        $d1['code'] = '';
        $d1['pos'] = 0;
        (new Tray())->where('id', $labelsinfo['trayid'])->save($d1);    //标记托盘号wu货物
        //减去已组盘数量
        $recordIn = new RecordIn();
        $rows = $recordIn->where('id', '=', $labelsinfo['recordid'])->find();
        $data = [];
        $data['num_bind'] = $rows['num_bind'] - $labelsinfo['goods_total'];
        $data['create_time'] = time();
        $recordInres = $recordIn->where('id', '=', $labelsinfo['recordid'])->save($data);
        unset($where);
        //if(!$recordInres) error(10032);
        success_loger(20013, $labelsinfo['tray_numbers'] . '进行解绑(扫标签号)', $labelsinfo);
        return $recordInres;
    }

    /**
     * 根据托盘号进行解绑
     * @param $param
     * @return bool
     */
    public function unbindingTrayNumbers($param)
    {
        if (!isset($param['tray_numbers'])) {
            error(10032, '收到托盘号：' . $param['tray_numbers']);
        }
        $tray_info = (new Tray())->where('numbers', $param['tray_numbers'])->find();
        if (!$tray_info) error(10032, '托盘未能找到');

        //组盘任务
        $recordcmdline = new RecordCmdlineModel();
        $where = [];
        $where[] = ['tray_numbers', '=', $param['tray_numbers']];
        $where[] = ['isend', '=', 0];
        $where[] = ['cmdtype', '=', 0];
        $is_cmd_line_count = $recordcmdline->where($where)->count();
        if ($is_cmd_line_count == 0) {//如果没有出库任务的话，验证是否重复，如果有出库任务，就可以直接解绑
            $where = [];
            $where[] = ['wareid', '>', 0];
            $where[] = ['trayid', '=', $tray_info['id']];
            $lcount = (new TrayGoods())->where($where)->count();
            unset($where);
            if ($lcount) {
                error(10032, '托盘已经在库内无法解绑,托盘号可能重复~');
            }
            //error(10032,'当前有出口任务未完成,请手动标记完成即可~');
        }


        //检查任务队列是否已经启动
        /*$where[] = ['tray_numbers','=',$labelsinfo['tray_numbers']];
        $where[] = ['isactivated','=',0];
        $where[] = ['isend','=',0];
        $redcdline = $recordcmdline->where($where)->find();
        unset($where);
        if(!isset($redcdline)) error(10020,'任务队列中没有符合要求的托盘，可能已经入库');*/
        if ($tray_info['numbers']) {
            //将对应的AGV点位上的托盘号给解绑掉
            $where = array();
            $where[] = ['tray_numbers', '=', $tray_info['numbers']];
            $tcount = (new DeviceAgvpos())->where($where)->count();
            if ($tcount) {

                $where1 = array();
                $where1[] = ['devicetype', '=', 2];
                $where1[] = ['traynumbers', '=', $tray_info['numbers']];
                $where1[] = ['status', '<>', 2];
                $taskno = (new CmdlineModel())->where($where1)->value('taskno');
                if ($taskno) {
                    (new DeviceAgvpos())->AgvResetPost($taskno);
                }

                $res = (new DeviceAgvpos())->where($where)->save([
                    'tray_numbers' => 0,
                    'islock' => 0,
                    'is_call' => 0,
                    'create_time' => time()
                ]);
            }


            $cmdline = new Cmdline();
            $where = [];
            $where[] = ['traynumbers', '=', $tray_info['numbers']];
            $where[] = ['status', '<>', 2];//未完成的不能删除
            $cmd = $cmdline->where($where)->delete();
            unset($where);

            $GoodsLabels = new GoodsLabels();
            $where = [];
            $where[] = ['trayid', '=', $tray_info['id']];//用托盘id
            $cmd = $GoodsLabels->where($where)->save(['trayid' => 0, 'tray_numbers' => 0, 'recordid' => 0]);

            unset($where);

            //删除任务队列
            $where = [];
            $where[] = ['tray_numbers', '=', $tray_info['numbers']];
            $where[] = ['isend', '=', 0];
            //删除主任务时候要将锁定状态变更可用
            $wareid = $recordcmdline->where($where)->value('wareid');
            if ($wareid) {
                (new Wareroom3d())->where('id', $wareid)->save(['status' => 0]);
            }
            $cmdtype = $recordcmdline->where($where)->value('cmdtype');
            $from_wcs = $recordcmdline->where($where)->value('from_wcs');
            unset($where);
            if ($cmdtype == 1 && $from_wcs) {
                $equipment2_type = substr($from_wcs, 0, 3);//获取设备号前3位
                //删除任务时候，取消任务线入库任务
                if (strtoupper($equipment2_type) == 'M20' || strtoupper($equipment2_type) == 'M30') {
                    $where4 = array();
                    $where4['title'] = $from_wcs;
                    $devconvey = new DeviceConvey();
                    $conveyinfo = $devconvey->where($where4)->find();
                    unset($where4);
                    if (!$conveyinfo) {
                        error(10032, '无设备名称' . $from_wcs);
                    }
                    $conCMD = new ConveyCMD($conveyinfo['convey_key']);
                    $conCMD->resetWcsInSend($conveyinfo['title']);
                }
            }

            unset($where);
            $where = [];
            $where[] = ['tray_numbers', '=', $tray_info['numbers']];
            $where[] = ['isend', '=', 0];
            $recordcmdline->where($where)->delete();


            unset($where);
        }
        //删除预写库存信息
        $traygoods = new TrayGoods();
        $where = [];
        $where[] = ['trayid', '=', $tray_info['id']];
        $tg_wareid = $traygoods->where($where)->value('wareid');
        if (intval($tg_wareid) > 0) {
            $success = (new Wareroom3d())->where('id', $tg_wareid)->update(['status' => 0, 'goods_status' => 0, 'create_time' => time()]);
            if (!$success) {
                error(10032, '解绑更新库位状态失败');
            }
        }
        $success = $traygoods->where($where)->delete();
        /*if(!$success){
            error(10020,'解绑删除库存失败');
        }*/
        unset($where);

        $labelsinfo = $this->field('id,recordid,goods_total')->where('trayid', '=', $tray_info['id'])->find();
        //清除关联表中的信息
        if (isset($labelsinfo['id']) && $labelsinfo['id'] > 0) {
            //清除标签绑定信息
            $res = $this->where('trayid', $tray_info['id'])->save(['trayid' => 0, 'tray_numbers' => 0, 'recordid' => 0]);//
            $recordlabel = new RecordLabels();
            $where = [];
            $where[] = ['labelsid', '=', $labelsinfo['id']];
            $where[] = ['recordid', '=', $labelsinfo['recordid']];
            $recordlabel->where($where)->delete();
            unset($where);
            //减去已组盘数量
            $recordIn = new RecordIn();
            $rows = $recordIn->where('id', '=', $labelsinfo['recordid'])->find();
            $data = [];
            $data['num_bind'] = $rows['num_bind'] - $labelsinfo['goods_total'];
            $data['create_time'] = time();
            $recordInres = $recordIn->where('id', '=', $labelsinfo['recordid'])->save($data);
            unset($where);
        }
        $d1 = [];
        $d1['goods'] = 0;
        $d1['code'] = '';
        $d1['pos'] = 0;
        $d1['create_time'] = time();
        $success = (new Tray())->where('id', $tray_info['id'])->save($d1);    //标记托盘号wu货物
        unset($d1);
        success_loger(20013, $param['tray_numbers'] . '-进行解绑(扫托盘号)', $param);
        return $success;
    }

    /**
     * 根据标签id打印单个标签
     * type =0,原拣选位  1=目标拣选位tray_numbers
     * $deviceid   打印机id
     */
    public function printByLabelId($param, $deviceid = 1)
    {
        if (!$param['id']) {
            error(10032, '标签id必须存在');
        }
        $id = $param['id'];
        $labelinfo = $this->where('id', $id)->find();
        //var_dump($this->getLastSql());
        if (!$labelinfo['id']) {
            error(10032, '标签数据不存在');
        }
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($labelinfo['templateid']);
        $print_res = $deviceprinter->printer($deviceid, $gtinfo['data'], $labelinfo);
        if ($print_res) {
            $data['print_count'] = $labelinfo['print_count'] + 1;
            $this->where('id', $id)->save($data);
        }
        return 1;
    }

    /**
     * 修改标签时候打印标签，
     * @param $param
     * @param int $deviceid
     * @return int
     */
    public function editPrintByLabelId($param, $deviceid = 1)
    {
        if (!$param['id']) {
            error(10032, '标签id必须存在');
        }
        $id = $param['id'];
        $labelinfo = $this->where('id', $id)->find();
        //var_dump($this->getLastSql());
        if (!$labelinfo['id']) {
            error(10032, '标签数据不存在');
        }
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($labelinfo['templateid']);
        $print_res = $deviceprinter->editPrinter($deviceid, $gtinfo['data'], $labelinfo);
        if ($print_res) {
            $data['print_count'] = $labelinfo['print_count'] + 1;
            $this->where('id', $id)->save($data);
        }
        return 1;
    }

    /**
     * MES打印根据标签id打印单个标签
     * type =0,原拣选位  1=目标拣选位tray_numbers
     */
    public function MesprintByLabelId($id, $ip)
    {
        if (!$id) {
            error(10032, '标签id必须存在');
        }
        $labelinfo = $this->where('id', $id)->find();
        if (!$labelinfo['id']) {
            error(10032, '标签数据不存在');
        }
        if (!$labelinfo['templateid']) {
            error(10032, '打印模板错误:' . $labelinfo['templateid']);
        }
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($labelinfo['templateid']);
        $DevicePrinterId = (new DevicePrinter())->where('ip', $ip)->value('id');
        if (!$DevicePrinterId) {
            error(10032, '打印机ip地址不存在~');
        }
        $print_res = $deviceprinter->printer($DevicePrinterId, $gtinfo['data'], $labelinfo);
        if ($print_res) {
            $data['print_count'] = $labelinfo['print_count'] + 1;
            $this->where('id', $id)->save($data);
        }
        return 1;
    }

    /**
     * 根据标签id打印单个标签
     * type =0,原拣选位  1=目标拣选位
     */
    public function printByLabelIdNumbers($param)
    {
        if (!$param['id']) {
            error(10032, '标签id必须存在');
        }
        if (!$param['tray_numbers']) {
            error(10032, 'tray_numbers托盘号必须存在');
        }
        $olid = $param['id'];//老的标签id
        $tray_numbers = $param['tray_numbers'];
        $is_print = $param['is_print'] ?? 0;
        $processid = (new RecordProcessModel())->getNumbersById(141);//四向车拣选目标位入库
        $RecordInId = (new RecordInModel())->addFourCarPickRecordIn($olid, $processid);//根据旧的标签进行生成入库一个单据
        if (!$RecordInId) {
            error(10032, '标签生成单据失败');
        }
        $tray_info = (new Tray())->getTaryByTrayno($tray_numbers);
        //生成新的标签id
        $lablesId_rows = $this->addPickLabels($olid, $tray_info['id'], $RecordInId, 1);

        foreach ($lablesId_rows as $v) {
            $labelinfo = $this->where('id', $v)->find();//新的标签打印数据
            if (!$labelinfo['id']) {
                error(10032, '标签数据不存在');
            }
            //是否自动打印，接口过来是自动打印
            if (!$is_print) {
                $this->getTemplateIdByLid($labelinfo);
            }
        }

        return true;
    }

    /**
     * 根据标签id打印单个标签
     * type =3  立体库2楼、3楼拣选
     */
    public function printFloor2ByLabelId($param)
    {
        if (!$param['id']) {
            error(10032, '标签id必须存在');
        }
        if (!$param['tray_numbers']) {
            error(10032, 'tray_numbers托盘号必须存在');
        }
        if (!$param['stationid']) {
            error(10032, '无工位id');
        }
        $olid = $param['id'];//老的标签id
        $tray_numbers = $param['tray_numbers'];
        $convey_title = $param['convey_title'];//设备号
        $is_print = $param['is_print'] ?? 0;
        $stationid = $param['stationid'];
        $deviceid = (new DevicePick())->where('id', $stationid)->value('printerid');//打印机设备id
        if (!$deviceid) {
            error(10032, '源拣选位打印机未配置,设备号' . $convey_title);
        }
        $labelinfo = $this->where('id', $olid)->find();
        if (!$labelinfo['id']) {
            error(10032, '标签数据不存在');
        }
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($labelinfo['templateid']);

        $print_res = $deviceprinter->printer($deviceid, $gtinfo['data'], $labelinfo);
        if ($print_res) {
            $data['print_count'] = $labelinfo['print_count'] + 1;
            $this->where('id', $olid)->save($data);
        }
        return 1;
    }

    /**
     * 根据标签id打印单个标签
     * 1=目标拣选位
     */
    public function printByLabelsFloor2Pick($param)
    {
        if (!$param['id']) {
            error(10032, '标签id必须存在');
        }
        if (!$param['tray_numbers']) {
            error(10032, 'tray_numbers托盘号必须存在');
        }
        if (!$param['stationid']) {
            error(10032, '无工位id');
        }
        $olid = $param['id'];//老的标签id
        $tray_numbers = $param['tray_numbers'];
        $convey_title = $param['convey_title'];//设备号
        $is_print = $param['is_print'] ?? 0;
        $stationid = $param['stationid'];
        $deviceid = (new DevicePick())->where('id', $stationid)->value('printerid');//打印机设备id
        /*if(!$deviceid){
            error(10032,'目标拣选打印机未配置,设备号'.$convey_title);
        }*/

        /*$sql_where = array();
        $sql_where[] = ['title','like','2楼入'.$convey_title];
        $RecordProcessNumbers = (new RecordProcessModel())->where($sql_where)->value('numbers');//根据设备号获取对应的业务流程编号*/
        $RecordProcessNumbers = (new RecordProcessModel())->getFloorInNumbers($convey_title);//根据设备号获取对应的业务流程编号
        $processid = (new RecordProcessModel())->getNumbersById($RecordProcessNumbers);//拣选目标位入库
        $tray_info = (new Tray())->getTaryByTrayno($tray_numbers);
        $RecordInId = (new RecordInModel())->addFloor2PickRecordIn($olid, $processid, 2, $tray_numbers);//根据旧的标签进行生成入库一个单据
        if (!$RecordInId) {
            error(10032, '标签生成单据失败');
        }
        //生成新的标签id
        $lablesId_rows = $this->addFloorPickLabelsPrint($olid, $tray_info['id'], $RecordInId, 1);
        $labelinfo = $this->where('id', $lablesId_rows['newlid'])->find()->toArray();//新的标签打印数据
        if (!$labelinfo['id']) {
            error(10032, '标签数据不存在');
        }
        //是否自动打印，接口过来是自动打印
        if (!$is_print && $deviceid) {
            $this->getTemplateIdByLid($labelinfo, $deviceid);//暂时关闭
        }
        return true;
    }

    /**
     * 根据标签id返回recordid
     * @param $id
     * @return mixed
     */
    public function getIdByRecordId($id)
    {
        return $this->where('id', $id)->value('recordid');
    }

    /**
     * 获取模板数据打印
     * @param $labelinfo
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTemplateIdByLid($labelinfo, $deviceid = 1)
    {
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($labelinfo['templateid']);
        //$deviceid=1;
        /*var_dump($deviceid);
        die();*/
        $print_res = $deviceprinter->printer($deviceid, $gtinfo['data'], $labelinfo);
        if ($print_res) {
            $data['print_count'] = $labelinfo['print_count'] + 1;
            $this->where('id', $labelinfo['id'])->save($data);
        }
        return true;
    }

    /**
     * 打印标签
     */
    public function printlabel($param)
    {

        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($param['templabelsid']);

        $list = json_decode($param['labelsid'], true);
        if (!is_array($list)) {
            error(10032, 'labelsid非数组');
        }
        foreach ($list as $item) {
            $labelinfo = $this->find($item);

            $print_res = $deviceprinter->printer($param['deviceid'], $gtinfo['data'], $labelinfo);

            if ($print_res) {
                $data['print_count'] = $labelinfo['print_count'] + 1;
                $this->where('id', $item)->save($data);
            }
            unset($print_res);
        }


        return;
    }


    /**
     * 打印标签
     */
    public function printLabelAll()
    {
        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtList = $goodslabelstemp->select()->toArray();
        foreach ($gtList as $item) {
            $labelinfo = (new GoodsLabels())->where('id', 1171)->find();
            $print_res = $deviceprinter->printer(1, $item['data'], $labelinfo);
            unset($print_res);
        }
        return $gtList;
    }

    /**
     * 修改标签质检状态
     */
    public function labeleditqt($param)
    {
        $where = array();
        if (isset($param['numbers'])) $where[] = ['numbers', '=', $param['numbers']];
        if (isset($param['pono'])) $where[] = ['pono', '=', $param['pono']];
        if (isset($param['poitem'])) $where[] = ['poitem', '=', $param['poitem']];
        if (isset($param['goods_batch'])) $where[] = ['goods_batch', '=', $param['goods_batch']];
        if (isset($param['goods_numbers'])) $where[] = ['goods_numbers', '=', $param['goods_numbers']];
        if (isset($param['qtstatus'])) {
            $data['qtstatus'] = intval($param['qtstatus']);
        } else {
            error(10015);
        }
        $rows = $this->where($where)->find();
        if ($rows['qtstatus'] == 1) {
            error(10032, '状态已经质检');
        }
        $res = $this->where($where)->save($data);
        $param['sql_where'] = $where;
        $param['save'] = $data;
        $param['is_save_success'] = $res;
        (new ApiLogModel())->addApiLog('labeleditqt', $param, 0);
        if (!$res) {
            error(10032, '更新失败');
        }
        return $res;
    }

    /**
     * 将原标签id转换成新的标签
     * @param $olid
     * @param $trayid
     * @param $recordid
     */
    public function addLabels($olid, $trayid, $recordid, $record_type = 1)
    {
        $info = $this->where('id', $olid)->find();
        $goods_total = $info['goods_total'] - $info['goods_pick_total'];
        if ($goods_total == 0) {//全部拣选完要进行删除旧的库存
            $success = (new TrayGoodsModel())->where('labelsid', $olid)->delete();//删除库存
        }
        //扣除老单据上标签数量
        $old_labels_success = $this->where('id', $olid)->save(['goods_total' => $goods_total]);
        if ($old_labels_success) {
            $sucees = (new TrayGoodsModel())->where('labelsid', $olid)->save(['total' => $goods_total]);
        }
        /*if($old_labels_success){
            $OldRecordIninfo = (new RecordIn())->where('id',$LabelsInfo['recordid'])->find();
            $i=[];
            $i['num_now']=$OldRecordIninfo['num_now']-$LabelsInfo['goods_pick_total'];
            $i['num_bind']=$OldRecordIninfo['num_bind']-$LabelsInfo['goods_pick_total'];
            (new RecordIn())->where('id',$OldRecordIninfo['id'])->save($i);
        }*/
        //将原标签生成新标签
        $sql_where = [];
        $sql_where[] = ['trayid', '=', $trayid];
        $sql_where[] = ['goodsid', '=', $info['goodsid']];
        $sql_where[] = ['record_type', '=', $record_type];
        //$sql_where[] = ['goods_total','=',$info['goods_total']];
        $sql_where[] = ['recordid', '=', $recordid];
        $labelsid = $this->where($sql_where)->value('id');
        if (!$labelsid) {
            $param = [];
            $param['numbers'] = $this->setNumbers();
            $param['templateid'] = $info['templateid'];
            $param['templateid'] = $info['templateid'];
            $param['processid'] = $info['processid'];
            $param['receivingid'] = $info['receivingid'];
            $param['record_type'] = $record_type;
            $param['sdvono'] = $info['sdvono'];
            $param['pono'] = $info['pono'];
            $param['poitem'] = $info['poitem'];
            $param['sendno'] = $info['sendno'];
            $param['senditem'] = $info['senditem'];
            $param['pdoinno'] = $info['pdoinno'];
            $param['pdoinitem'] = $info['pdoinitem'];
            $param['pdooutno'] = $info['pdooutno'];
            $param['pdooutitem'] = $info['pdooutitem'];
            $param['bomno'] = $info['bomno'];
            $param['bomitem'] = $info['bomitem'];
            $param['dvono'] = $info['dvono'];
            $param['dvoitem'] = $info['dvoitem'];
            $param['sono'] = $info['sono'];
            $param['soitem'] = $info['soitem'];
            $param['wareid'] = 0;
            $param['ware_type'] = $info['ware_type'];
            $tray_info = (new Tray())->where('id', $trayid)->find();
            $param['trayid'] = $tray_info['id'];//扫码托盘
            $param['tray_numbers'] = $tray_info['numbers'];
            $param['goodsid'] = $info['goodsid'];
            $param['goods_numbers'] = $info['goods_numbers'];
            $param['goods_title'] = $info['goods_title'];
            $param['goods_batch'] = $info['goods_batch'];
            $param['goods_total'] = $info['goods_pick_total'] ?? 0;//拣选数量
            $param['goods_pick_total'] = 0;//拣选数量
            $param['qtstatus'] = $info['qtstatus'];
            $param['prodate'] = $info['prodate'];
            $param['expdate'] = $info['expdate'];
            $param['indate_now'] = time();
            $param['outdate_now'] = $info['outdate_now'];
            $param['recdate'] = $info['recdate'];
            $param['recdate'] = $info['recdate'];
            $param['sadate'] = $info['sadate'];
            $param['factory'] = $info['factory'];
            $param['storage'] = $info['storage'];
            $param['supplierid'] = $info['supplierid'];
            $param['supplier_title'] = $info['supplier_title'];
            $param['supplier_batch'] = $info['supplier_batch'];
            $param['supplier_code'] = $info['supplier_code'];
            $param['print_count'] = 0;
            $param['classes'] = $info['classes'];
            $param['workers'] = $info['workers'];
            $param['workcenter'] = $info['workcenter'];
            $param['num_total'] = 0;
            $param['num_index'] = 0;
            $param['move_status'] = 0;
            $param['recordid'] = $recordid;
            $param['create_time'] = time();
            //生成新的标签
            $labelsid = $this->insertGetId($param);
            if (!$labelsid) {
                error(10032, '生成拣选标签失败~');
            }
        }
        //请求打印机打印
        $url = 'http://10.80.10.14:8282/index.php/goodslabels/printbylabelid?id=' . $labelsid;
        $r = $this->getCurl($url);
        return $labelsid;
    }

    /**
     * 拣选目标位标签转换下
     * @param $olid  老标签id
     * @param $trayid  托盘id
     * @param $RecordInId  入库单据id
     */
    public function addPickLabels($olid, $trayid, $recordid, $record_type = 1)
    {
        $info = $this->where('id', $olid)->find();
        /* if($info['goods_pick_total']>0){
            $goods_total= $info['goods_total']-$info['goods_pick_total'];
            if($goods_total<=0){//全部拣选完要进行删除旧的库存
                //$success=(new TrayGoodsModel())->where('labelsid',$olid)->delete();//删除库存
                $goods_total = 0;
            }
            //扣除老单据上标签数量
            $old_labels_success = $this->where('id',$olid)->save(['goods_total'=>$goods_total,'goods_pick_total'=>0,'record_type'=>$record_type,'create_time'=>time()]);//   点击打印时候不修改这两个参数，等点击拣选完成修改下
            if($old_labels_success){
                $success = (new TrayGoodsModel())->where('labelsid',$olid)->save(['total'=>$goods_total]);//库存修改掉
                //$success = (new GoodsLabelsModel())->where('id',$olid)->save(['goods_pick_total'=>0]);//标签拣选量修改掉
            }
        }*/
        $RecordInInfo = (new RecordInModel())->where('id', $recordid)->find();
        //将原标签生成新标签
        $sql_where = [];
        $sql_where[] = ['trayid', '=', $trayid];
        $sql_where[] = ['recordid', '=', $recordid];
        $sql_where[] = ['record_type', '=', $record_type];
        $labelsid = $this->where($sql_where)->value('id');
        if (!$labelsid) {
            $param = [];
            $param['numbers'] = $this->setNumbers();
            $param['templateid'] = $info['templateid'];
            $param['templateid'] = $info['templateid'];
            $param['processid'] = $RecordInInfo['processid'];
            $param['receivingid'] = $info['receivingid'];
            $param['record_type'] = $record_type;
            $param['sdvono'] = $info['sdvono'];
            $param['pono'] = $info['pono'];
            $param['poitem'] = $info['poitem'];
            $param['sendno'] = $info['sendno'];
            $param['senditem'] = $info['senditem'];
            $param['pdoinno'] = $info['pdoinno'];
            $param['pdoinitem'] = $info['pdoinitem'];
            $param['pdooutno'] = $info['pdooutno'];
            $param['pdooutitem'] = $info['pdooutitem'];
            $param['bomno'] = $info['bomno'];
            $param['bomitem'] = $info['bomitem'];
            $param['dvono'] = $info['dvono'];
            $param['dvoitem'] = $info['dvoitem'];
            $param['sono'] = $info['sono'];
            $param['soitem'] = $info['soitem'];
            $param['wareid'] = 0;
            $param['ware_type'] = $info['ware_type'];
            $tray_info = (new Tray())->where('id', $trayid)->find();
            $param['trayid'] = $tray_info['id'];//扫码托盘
            $param['tray_numbers'] = $tray_info['numbers'];
            $param['goodsid'] = $info['goodsid'];
            $param['goods_numbers'] = $info['goods_numbers'];
            $param['goods_title'] = $info['goods_title'];
            $param['goods_batch'] = $info['goods_batch'];
            $param['goods_total'] = $info['goods_pick_total'] ?? 0;//拣选数量
            $param['goods_pick_total'] = 0;//拣选数量
            $param['qtstatus'] = $info['qtstatus'];
            $param['prodate'] = $info['prodate'];
            $param['expdate'] = $info['expdate'];
            $param['indate_now'] = time();
            $param['outdate_now'] = $info['outdate_now'];
            $param['recdate'] = $info['recdate'];
            $param['recdate'] = $info['recdate'];
            $param['sadate'] = $info['sadate'];
            $param['factory'] = $info['factory'];
            $param['storage'] = $info['storage'];
            $param['supplierid'] = $info['supplierid'];
            $param['supplier_title'] = $info['supplier_title'];
            $param['supplier_batch'] = $info['supplier_batch'];
            $param['supplier_code'] = $info['supplier_code'];
            $param['print_count'] = 0;
            $param['classes'] = $info['classes'];
            $param['workers'] = $info['workers'];
            $param['workcenter'] = $info['workcenter'];
            $param['num_total'] = 0;
            $param['num_index'] = 0;
            $param['move_status'] = 0;
            $param['isposting'] = 1;//拣选托盘不用过账
            $param['recordid'] = $RecordInInfo['id'];
            $param['create_time'] = time();
            //生成新的标签
            $labelsid = $this->insertGetId($param);
            if (!$labelsid) {
                error(10032, '生成拣选标签失败~');
            }
        }
        return ['olid' => $olid, 'newlid' => $labelsid];
    }

    /**
     * 2楼拣选点击打印按钮
     * @param $olid
     * @param $trayid
     * @param $recordid
     * @param int $record_type
     * @return array
     */
    public function addFloorPickLabelsPrint($olid, $trayid, $recordid, $record_type = 1)
    {
        $info = $this->where('id', $olid)->find();
        if ($info['goods_pick_total'] > 0) {//测试2楼时候原标签暂时不动，防止数量被扣减
            $goods_total = $info['goods_total'] - $info['goods_pick_total'];
            if ($goods_total <= 0) {//全部拣选完要进行删除旧的库存
                //$success=(new TrayGoodsModel())->where('labelsid',$olid)->delete();//删除库存
                $goods_total = 0;
            }
            //扣除老单据上标签数量
            $old_labels_success = $this->where('id', $olid)->save(['goods_total' => $goods_total, 'record_type' => 5, 'create_time' => time()]);//,'goods_pick_total'=>0
            if ($old_labels_success) {
                $success = (new TrayGoodsModel())->where('labelsid', $olid)->save(['total' => $goods_total, 'create_time' => time()]);//库存修改掉
            }
        }
        $RecordInInfo = (new RecordInModel())->where('id', $recordid)->find();
        //将原标签生成新标签
        $sql_where = [];
        $sql_where[] = ['trayid', '=', $trayid];
        $sql_where[] = ['recordid', '=', $recordid];
        $sql_where[] = ['record_type', '=', $record_type];
        $labelsid = $this->where($sql_where)->value('id');
        if (!$labelsid) {
            $param = [];
            $param['numbers'] = $this->setNumbers();
            $param['templateid'] = $info['templateid'];
            $param['templateid'] = $info['templateid'];
            $param['processid'] = $RecordInInfo['processid'];
            $param['receivingid'] = $info['receivingid'];
            $param['record_type'] = $record_type;
            $param['sdvono'] = $info['sdvono'];
            $param['pono'] = $info['pono'];
            $param['poitem'] = $info['poitem'];
            $param['sendno'] = $info['sendno'];
            $param['senditem'] = $info['senditem'];
            $param['pdoinno'] = $info['pdoinno'];
            $param['pdoinitem'] = $info['pdoinitem'];
            $param['pdooutno'] = $info['pdooutno'];
            $param['pdooutitem'] = $info['pdooutitem'];
            $param['bomno'] = $info['bomno'];
            $param['bomitem'] = $info['bomitem'];
            $param['dvono'] = $info['dvono'];
            $param['dvoitem'] = $info['dvoitem'];
            $param['sono'] = $info['sono'];
            $param['soitem'] = $info['soitem'];
            $param['wareid'] = 0;
            $param['ware_type'] = $info['ware_type'];
            $tray_info = (new Tray())->where('id', $trayid)->find();
            $param['trayid'] = $tray_info['id'];//扫码托盘
            $param['tray_numbers'] = $tray_info['numbers'];
            $param['goodsid'] = $info['goodsid'];
            $param['goods_numbers'] = $info['goods_numbers'];
            $param['goods_title'] = $info['goods_title'];
            $param['goods_batch'] = $info['goods_batch'];
            $param['goods_total'] = $info['goods_pick_total'] ?? 0;//拣选数量
            $param['goods_pick_total'] = 0;//拣选数量
            $param['qtstatus'] = $info['qtstatus'];
            $param['prodate'] = $info['prodate'];
            $param['expdate'] = $info['expdate'];
            $param['indate_now'] = time();
            $param['outdate_now'] = $info['outdate_now'];
            $param['recdate'] = $info['recdate'];
            $param['recdate'] = $info['recdate'];
            $param['sadate'] = $info['sadate'];
            $param['factory'] = $info['factory'];
            $param['storage'] = $info['storage'];
            $param['supplierid'] = $info['supplierid'];
            $param['supplier_title'] = $info['supplier_title'];
            $param['supplier_batch'] = $info['supplier_batch'];
            $param['supplier_code'] = $info['supplier_code'];
            $param['print_count'] = 0;
            $param['classes'] = $info['classes'];
            $param['workers'] = $info['workers'];
            $param['workcenter'] = $info['workcenter'];
            $param['num_total'] = 0;
            $param['num_index'] = 0;
            $param['move_status'] = 0;
            $param['isposting'] = 1;//拣选托盘不用过账
            $param['recordid'] = $RecordInInfo['id'];
            $param['create_time'] = time();
            //生成新的标签
            $labelsid = $this->insertGetId($param);
            if (!$labelsid) {
                error(10032, '生成拣选标签失败~');
            }
        }
        return ['olid' => $olid, 'newlid' => $labelsid];
    }

    public function addFloorPickLabels($olid, $trayid, $recordid, $record_type = 1)
    {
        $info = $this->where('id', $olid)->find();
        //var_dump($olid.'--------'.$info['goods_total']);
        if ($info['goods_pick_total'] > 0) {//测试2楼时候原标签暂时不动，防止数量被扣减
            $goods_total = $info['goods_total'] - $info['goods_pick_total'];
            //var_dump('goods_pick_total='.$info['goods_pick_total']);
            //var_dump('$goods_total='.$goods_total);
            if ($goods_total <= 0) {//全部拣选完要进行删除旧的库存
                //$success=(new TrayGoodsModel())->where('labelsid',$olid)->delete();//删除库存
                $goods_total = 0;
            }
            //扣除老单据上标签数量
            $old_labels_success = $this->where('id', $olid)->save(['goods_total' => $goods_total, 'record_type' => 5, 'create_time' => time()]);//,'goods_pick_total'=>0
            if ($old_labels_success) {
                $success = (new TrayGoodsModel())->where('labelsid', $olid)->save(['total' => $goods_total, 'create_time' => time()]);//库存修改掉
                //$success = (new GoodsLabelsModel())->where('id',$olid)->save(['goods_pick_total'=>0]);//标签拣选量修改掉
            }
        }
        $RecordInInfo = (new RecordInModel())->where('id', $recordid)->find();
        //将原标签生成新标签
        $sql_where = [];
        $sql_where[] = ['trayid', '=', $trayid];
        $sql_where[] = ['recordid', '=', $recordid];
        $sql_where[] = ['record_type', '=', $record_type];
        $labelsid = $this->where($sql_where)->value('id');
        if (!$labelsid) {
            $param = [];
            $param['numbers'] = $this->setNumbers();
            $param['templateid'] = $info['templateid'];
            $param['templateid'] = $info['templateid'];
            $param['processid'] = $RecordInInfo['processid'];
            $param['receivingid'] = $info['receivingid'];
            $param['record_type'] = $record_type;
            $param['sdvono'] = $info['sdvono'];
            $param['pono'] = $info['pono'];
            $param['poitem'] = $info['poitem'];
            $param['sendno'] = $info['sendno'];
            $param['senditem'] = $info['senditem'];
            $param['pdoinno'] = $info['pdoinno'];
            $param['pdoinitem'] = $info['pdoinitem'];
            $param['pdooutno'] = $info['pdooutno'];
            $param['pdooutitem'] = $info['pdooutitem'];
            $param['bomno'] = $info['bomno'];
            $param['bomitem'] = $info['bomitem'];
            $param['dvono'] = $info['dvono'];
            $param['dvoitem'] = $info['dvoitem'];
            $param['sono'] = $info['sono'];
            $param['soitem'] = $info['soitem'];
            $param['wareid'] = 0;
            $param['ware_type'] = $info['ware_type'];
            $tray_info = (new Tray())->where('id', $trayid)->find();
            $param['trayid'] = $tray_info['id'];//扫码托盘
            $param['tray_numbers'] = $tray_info['numbers'];
            $param['goodsid'] = $info['goodsid'];
            $param['goods_numbers'] = $info['goods_numbers'];
            $param['goods_title'] = $info['goods_title'];
            $param['goods_batch'] = $info['goods_batch'];
            $param['goods_total'] = $info['goods_pick_total'] ?? 0;//拣选数量
            $param['goods_pick_total'] = 0;//拣选数量
            $param['qtstatus'] = $info['qtstatus'];
            $param['prodate'] = $info['prodate'];
            $param['expdate'] = $info['expdate'];
            $param['indate_now'] = time();
            $param['outdate_now'] = $info['outdate_now'];
            $param['recdate'] = $info['recdate'];
            $param['recdate'] = $info['recdate'];
            $param['sadate'] = $info['sadate'];
            $param['factory'] = $info['factory'];
            $param['storage'] = $info['storage'];
            $param['supplierid'] = $info['supplierid'];
            $param['supplier_title'] = $info['supplier_title'];
            $param['supplier_batch'] = $info['supplier_batch'];
            $param['supplier_code'] = $info['supplier_code'];
            $param['print_count'] = 0;
            $param['classes'] = $info['classes'];
            $param['workers'] = $info['workers'];
            $param['workcenter'] = $info['workcenter'];
            $param['num_total'] = 0;
            $param['num_index'] = 0;
            $param['move_status'] = 0;
            $param['isposting'] = 1;//拣选托盘不用过账
            $param['recordid'] = $RecordInInfo['id'];
            $param['create_time'] = time();
            //生成新的标签
            $labelsid = $this->insertGetId($param);
            if (!$labelsid) {
                error(10032, '生成拣选标签失败~');
            }
        }
        return ['olid' => $olid, 'newlid' => $labelsid];
    }

    /**
     * 生成标签号
     * @return string
     */
    public function setNumbers()
    {
        return uniqid(rand(100000, 999999));
    }

    /**
     * 手持机复制标签，将原标签号，数量，业务流程，生成一个新的标签
     * @param $param
     */
    public function setCopyLabelsByNumbers($param)
    {
        $numbers = $param['lid_numbers'];
        $total = $param['total'] ?? 0;
        $processId = $param['processId'];
        /*if($total<=0){
            error(10032,'数量必须>0~');
        }*/
        $info = $this->where('numbers', $numbers)->find();
        if (empty($info)) {
            error(10032, '无原标签数据~');
        }
        $RecordProcessRows = (new RecordProcessModel())->getId($processId);
        if (empty($RecordProcessRows)) {
            error(10032, '无流程数据~');
        }

        $param = [];
        $param['numbers'] = $this->setNumbers();
        $param['templateid'] = $info['templateid'];
        $param['processid'] = $processId;
        $param['record_type'] = $info['record_type'];
        $param['sdvono'] = $info['sdvono'];
        $param['pono'] = $info['pono'];
        $param['poitem'] = $info['poitem'];
        $param['sendno'] = $info['sendno'];
        $param['senditem'] = $info['senditem'];
        $param['pdoinno'] = $info['pdoinno'];
        $param['pdoinitem'] = $info['pdoinitem'];
        $param['pdooutno'] = $info['pdooutno'];
        $param['pdooutitem'] = $info['pdooutitem'];
        $param['bomno'] = $info['bomno'];
        $param['bomitem'] = $info['bomitem'];
        $param['dvono'] = $info['dvono'];
        $param['dvoitem'] = $info['dvoitem'];
        $param['sono'] = $info['sono'];
        $param['soitem'] = $info['soitem'];
        $param['wareid'] = $info['wareid'];
        $param['ware_type'] = $info['ware_type'];
        $param['trayid'] = 0;//扫码托盘
        $param['tray_numbers'] = '';
        $param['goodsid'] = $info['goodsid'];
        $param['goods_numbers'] = $info['goods_numbers'];
        $param['goods_title'] = $info['goods_title'];
        $param['goods_batch'] = $info['goods_batch'];
        $param['goods_total'] = $total;//拣选数量
        $param['goods_pick_total'] = 0;//拣选数量
        $param['qtstatus'] = $info['qtstatus'];
        $param['prodate'] = $info['prodate'];
        $param['expdate'] = $info['expdate'];
        $param['indate_now'] = time();
        $param['outdate_now'] = $info['outdate_now'];
        $param['recdate'] = $info['recdate'];
        $param['recdate'] = $info['recdate'];
        $param['sadate'] = $info['sadate'];
        $param['factory'] = $info['factory'];
        $param['storage'] = $info['storage'];
        $param['supplierid'] = $info['supplierid'];
        $param['supplier_title'] = $info['supplier_title'];
        $param['supplier_batch'] = $info['supplier_batch'];
        $param['supplier_code'] = $info['supplier_code'];
        $param['print_count'] = 0;
        $param['classes'] = $info['classes'];
        $param['workers'] = $info['workers'];
        $param['workcenter'] = $info['workcenter'];
        $param['num_total'] = $info['num_total'];
        $param['num_index'] = $info['num_index'];
        $param['move_status'] = 0;
        $param['isposting'] = 1;//拣选托盘不用过账
        $param['recordid'] = $info['recordid'];
        $param['create_time'] = time();
        //生成新的标签
        $labelsid = $this->insertGetId($param);
        if (!$labelsid) {
            error(10032, '新标签失败~');
        }
        return $labelsid;
    }

    /**
     * 标记出库对标签进行标记，保持流水日志
     * @param $labelsid  标签id
     * @param $res   任务列表集合
     */
    public function setCmdLogOutByLabelsStatus($labelsid, $res)
    {
        $log = [];
        $log['tray_numbers'] = $res['tray_numbers'];
        $log['recordid'] = $res['recordid'];
        $log['record_type'] = $res['record_type'];
        $log['cmdtype'] = $res['cmdtype'];
        $log['process_id'] = $res['process_id'];
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 1;
        //标记标签
        $success = 0;
        $where1 = array();
        $where1[] = ['id', '=', $labelsid];
        $Label_list = $this->where($where1)->select();
        $goods_total = 0;
        foreach ($Label_list as $v) {
            $log['goods_numbers'] = $v['goods_numbers'];
            $log['goods_title'] = $v['goods_title'];
            $log['goods_batch'] = $v['goods_batch'];
            $log['goods_label'] = $v['numbers'];
            $log['templateid'] = $v['templateid'];
            $log['supplier_title'] = $v['supplier_title'];
            $log['supplier_batch'] = $v['supplier_batch'];
            $log['outdate_now'] = time();
            $log['factory'] = $v['factory'];
            $log['storage'] = $v['storage'];
            $log['classes'] = $v['classes'];
            $log['workers'] = $v['workers'];
            $log['qtstatus'] = $v['qtstatus'];
            $log['ch_num'] = $v['goods_total'];
            $room3dinfo = (new Wareroom3d())->where('id', $v['wareid'])->find();
            $log['wareroom_zone'] = $room3dinfo['zone'];
            $log['wareroom_u'] = $room3dinfo['u'];
            $log['wareroom_x'] = $room3dinfo['x'];
            $log['wareroom_y'] = $room3dinfo['y'];
            $log['wareroom_z'] = $room3dinfo['z'];
            $log['create_time'] = time();
            $success = (new GoodsLog())->insertGetId($log);
            $success = $this->where('id', $v['id'])->update([
                'move_status' => 2,
                'record_type' => 2,
                'outdate_now' => time(),
                'wareid' => 0,
                'trayid' => 0,
                'tray_numbers' => '',
                'pdooutno' => '']);
        }
        return $success;
    }

    /**
     * 针对拣选任务，record_type=5，不需要改变，要不然对应的拣选任务列表会不显示
     * @param $labelsid  标签id
     * @param $res   任务列表集合
     */
    public function setCmdLogPickByLabelsStatus($labelsid, $res)
    {
        $log = [];
        $log['tray_numbers'] = $res['tray_numbers'];
        $log['recordid'] = $res['recordid'];
        $log['record_type'] = $res['record_type'];
        $log['cmdtype'] = $res['cmdtype'];
        $log['process_id'] = $res['process_id'];
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 1;
        //标记标签
        $where1 = array();
        $where1[] = ['id', '=', $labelsid];
        $Label_list = $this->where($where1)->select();
        $goods_total = 0;
        foreach ($Label_list as $v) {
            $log['goods_numbers'] = $v['goods_numbers'];
            $log['goods_title'] = $v['goods_title'];
            $log['goods_batch'] = $v['goods_batch'];
            $log['goods_label'] = $v['numbers'];
            $log['templateid'] = $v['templateid'];
            $log['supplier_title'] = $v['supplier_title'];
            $log['supplier_batch'] = $v['supplier_batch'];
            $log['outdate_now'] = time();
            $log['factory'] = $v['factory'];
            $log['storage'] = $v['storage'];
            $log['classes'] = $v['classes'];
            $log['workers'] = $v['workers'];
            $log['qtstatus'] = $v['qtstatus'];
            $log['ch_num'] = $v['goods_total'];
            $room3dinfo = (new Wareroom3d())->where('id', $v['wareid'])->find();
            $log['wareroom_zone'] = $room3dinfo['zone'];
            $log['wareroom_u'] = $room3dinfo['u'];
            $log['wareroom_x'] = $room3dinfo['x'];
            $log['wareroom_y'] = $room3dinfo['y'];
            $log['wareroom_z'] = $room3dinfo['z'];
            $log['create_time'] = time();
            $success = (new GoodsLog())->insertGetId($log);
            $success = $this->where('id', $v['id'])->update(['move_status' => 2, 'outdate_now' => time(), 'wareid' => 0, 'create_time' => time()]);
        }
    }

    /**
     * 任务完成后根据标签号进行库存日志
     * @param $labelsid
     * @param $res
     */
    public function saveGoodsLog($labelsid, $res)
    {
        $log = [];
        $log['tray_numbers'] = $res['tray_numbers'];
        $log['recordid'] = $res['recordid'];
        $log['record_type'] = $res['record_type'];
        $log['cmdtype'] = $res['cmdtype'];
        $log['process_id'] = $res['process_id'];
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 1;
        //标记标签
        $Label_list = $this->where('id', $labelsid)->select();
        foreach ($Label_list as $v) {
            $log['goods_numbers'] = $v['goods_numbers'];
            $log['goods_title'] = $v['goods_title'];
            $log['goods_batch'] = $v['goods_batch'];
            $log['goods_label'] = $v['numbers'];
            $log['templateid'] = $v['templateid'];
            $log['supplier_title'] = $v['supplier_title'];
            $log['supplier_batch'] = $v['supplier_batch'];
            $log['indate_now'] = time();
            $log['factory'] = $v['factory'];
            $log['storage'] = $v['storage'];
            $log['classes'] = $v['classes'];
            $log['workers'] = $v['workers'];
            $log['qtstatus'] = $v['qtstatus'];
            $log['ch_num'] = $v['goods_total'];
            $room3dinfo = (new Wareroom3d())->where('id', $res['wareid'])->find();
            $log['wareroom_zone'] = $room3dinfo['zone'];
            $log['wareroom_u'] = $room3dinfo['u'];
            $log['wareroom_x'] = $room3dinfo['x'];
            $log['wareroom_y'] = $room3dinfo['y'];
            $log['wareroom_z'] = $room3dinfo['z'];
            $log['create_time'] = time();
            $success = (new GoodsLog())->insertGetId($log);
        }
        return $success;
    }

    /**
     * 标记入库对标签进行标记，保持流水日志
     * @param $trayid
     * @param $recordid
     * @param $res
     */
    public function setCmdLogInStatus($trayid, $labelsid, $res)
    {
        $log = [];
        $log['tray_numbers'] = $res['tray_numbers'];
        $log['recordid'] = $res['recordid'];
        $log['record_type'] = $res['record_type'];
        $log['cmdtype'] = $res['cmdtype'];
        $log['process_id'] = $res['process_id'];
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 1;
        //标记标签
        $Label_list = $this->where('id', $labelsid)->select();
        if (!$Label_list->isEmpty()) {
            foreach ($Label_list as $v) {
                $log['goods_numbers'] = $v['goods_numbers'];
                $log['goods_title'] = $v['goods_title'];
                $log['goods_batch'] = $v['goods_batch'];
                $log['goods_label'] = $v['numbers'];
                $log['templateid'] = $v['templateid'];
                $log['supplier_title'] = $v['supplier_title'];
                $log['supplier_batch'] = $v['supplier_batch'];
                $log['indate_now'] = time();
                $log['factory'] = $v['factory'];
                $log['storage'] = $v['storage'];
                $log['classes'] = $v['classes'];
                $log['workers'] = $v['workers'];
                $log['qtstatus'] = $v['qtstatus'];
                $log['start_postion'] = $v['start_postion'] ?? '';
                $log['ch_num'] = $v['goods_total'];
                $room3dinfo = (new Wareroom3d())->where('id', $res['wareid'])->find();
                $log['wareroom_zone'] = $room3dinfo['zone'];
                $log['wareroom_u'] = $room3dinfo['u'];
                $log['wareroom_x'] = $room3dinfo['x'];
                $log['wareroom_y'] = $room3dinfo['y'];
                $log['wareroom_z'] = $room3dinfo['z'];
                $log['create_time'] = time();
                $success = (new GoodsLog())->insertGetId($log);
                $success = $this->where('id', $v['id'])->update(['move_status' => 2, 'indate_now' => time(), 'wareid' => $res['wareid'], 'trayid' => $trayid, 'create_time' => time()]);
                //更新首次入库时间,更新到标签上
                $where = [];
                $where[] = ['id', '=', $v['id']];
                $this->where($where)->update(['indate' => time(), 'create_time' => time()]);//每次更新入库日期
            }
        }

    }

    /**
     * 移库时候，记录日志下
     * @param $taskno 任务号
     * @param $trayno 托盘号
     * @param $wareid 分配移库坐标
     */
    public function setCmdLogMoveStatus($taskno, $trayno, $wareid)
    {
        $trayid = (new Tray())->where('numbers', $trayno)->value('id');
        if (!$trayid) {
            return false;
        }
        $TrayGoodsModel = (new TrayGoodsModel())->where('trayid', $trayid)->find();
        if (!$TrayGoodsModel) {
            return false;
        }
        $labelsid = $TrayGoodsModel['labelsid'];
        //$wareid = $TrayGoodsModel['wareid'];
        $log = [];
        $log['tray_numbers'] = $trayno;
        $log['recordid'] = 0;
        $log['record_type'] = 3;
        $log['cmdtype'] = 2;
        $log['process_id'] = 0;
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 0;
        //标记标签
        $Label_list = $this->where('id', $labelsid)->select();
        $success = 0;
        if (!$Label_list->isEmpty()) {
            foreach ($Label_list as $v) {
                $log['goods_numbers'] = $v['goods_numbers'];
                $log['goods_title'] = $v['goods_title'] . '-移库-' . $taskno;
                $log['goods_desc'] = $v['goods_numbers'] . '_MOVE';
                $log['goods_batch'] = $v['goods_batch'];
                $log['goods_label'] = $v['numbers'];
                $log['templateid'] = $v['templateid'];
                $log['supplier_title'] = $v['supplier_title'];
                $log['supplier_batch'] = $v['supplier_batch'];
                $log['indate_now'] = time();
                $log['factory'] = $v['factory'];
                $log['storage'] = $v['storage'];
                $log['classes'] = $v['classes'];
                $log['workers'] = $v['workers'];
                $log['qtstatus'] = $v['qtstatus'];
                $log['ch_num'] = $v['goods_total'];
                $room3dinfo = (new Wareroom3d())->where('id', $wareid)->find();
                $log['wareroom_zone'] = $room3dinfo['zone'];
                $log['wareroom_u'] = $room3dinfo['u'];
                $log['wareroom_x'] = $room3dinfo['x'];
                $log['wareroom_y'] = $room3dinfo['y'];
                $log['wareroom_z'] = $room3dinfo['z'];
                $log['create_time'] = time();
                $success = (new GoodsLog())->insertGetId($log);
                //$success = $this->where('id',$v['id'])->update(['move_status'=>2,'indate_now'=>time(),'wareid'=>$wareid,'trayid'=>$trayid,'create_time'=>time()]);
                //更新首次入库时间,更新到标签上
                /*$where = [];
                $where[]=['id','=',$v['id']];
                $this->where($where)->update(['indate'=>time(),'create_time'=>time()]);//每次更新入库日期*/
            }
            return $success;
        } else {
            $log['goods_numbers'] = '移库-' . $taskno;
            $log['goods_title'] = '移库-' . $taskno;
            $log['goods_desc'] = $taskno . '_MOVE';
            $log['indate_now'] = time();
            $room3dinfo = (new Wareroom3d())->where('id', $wareid)->find();
            $log['wareroom_zone'] = $room3dinfo['zone'];
            $log['wareroom_u'] = $room3dinfo['u'];
            $log['wareroom_x'] = $room3dinfo['x'];
            $log['wareroom_y'] = $room3dinfo['y'];
            $log['wareroom_z'] = $room3dinfo['z'];
            $log['create_time'] = time();
            $success = (new GoodsLog())->insertGetId($log);
            return $success;
        }
        return -99;
    }

    /**
     * 修改拣选计算要拣选的数量
     * @param $id
     * @param $goods_total
     * @param $goods_pick_total
     * @return bool
     */
    public function setGoodsPickTotal($id, $goods_total, $goods_pick_total)
    {
        return $this->where('id', $id)->save(['goods_total' => $goods_total, 'goods_pick_total' => $goods_pick_total, 'create_time' => time()]);
    }

    /**
     * 根据托盘号推出来，拣选标签
     * @param $id
     * @param $goods_total
     * @param $goods_pick_total
     */
    public function getTrayNumber($tray_numbers, $record_type = 5)
    {
        $sql_where = [];
        $sql_where[] = ['tray_numbers', '=', $tray_numbers];
        $sql_where[] = ['record_type', '=', $record_type];
        return $this->where($sql_where)->find();
    }

    /*
     * 拣选位置自动打印标签
     */
    public function getCurl($url)
    {
        $curl = curl_init();  //初始化
        //设置选项，包括URL
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 50);           //设置超时时间
        $data = curl_exec($curl);                   //运行curl
        curl_close($curl);
        return $data;
    }

    public function addRecordInLabes_bak($param)
    {
        if (!$param['id']) {
            error(10032, '生产成品入库id为空');
        }
        $WorkInModelInfo = (new WorkInModel())->where('id', $param['id'])->find();
        $goodslabelstemplate = new GoodsLabelsTemplate();
        if (!$param['labelstempid']) {
            error(10032, '标签模板id为空');
        }
        $labelstp = $goodslabelstemplate->find($param['labelstempid']);

        $GoodsBaseInfo = (new GoodsBase())->where('numbers', $WorkInModelInfo['goods_numbers'])->find();
        if (!$GoodsBaseInfo) {
            error(10032, '基础物料不存在');
        }
        $sql_where = [];
        $sql_where[] = ['numbers', '=', $WorkInModelInfo['out_numbers']];
        $sql_where[] = ['rows', '=', $WorkInModelInfo['out_rows']];
        $RecordInId = (new RecordInModel())->where($sql_where)->value('id');
        if (!$RecordInId) {
            error(10032, '入库单据未生成出来');
        }

        //填充标签对应的数据
        $toparam['numbers'] = $this->setNumbers();
        $toparam['recordid'] = $RecordInId;
        $toparam['record_type'] = 1;
        $toparam['goods_total'] = $WorkInModelInfo['num_plan'];//每托数量
        $toparam['receivingid'] = 0;

        $toparam['goods_batch'] = $WorkInModelInfo['goods_batch'] ?? date('Ymd'); //物料批次
        $toparam['goods_numbers'] = $GoodsBaseInfo['numbers']; //物料编号
        $toparam['goods_title'] = $GoodsBaseInfo['title']; //物料名称
        //$toparam['supplier_batch'] = $WorkInModelInfo['supplier_batch']; //供应商批次
        //$toparam['supplier_title'] = $WorkInModelInfo['supplier_title']; //供应商名称
        $toparam['goods_description'] = $GoodsBaseInfo['description'];
        $toparam['goodsid'] = $WorkInModelInfo['goodsid'];//基础物料ID
        $toparam['processid'] = $labelstp['processid'];//业务流程
        $toparam['templateid'] = $labelstp['id'];//打印模板ID
        $toparam['pono'] = $WorkInModelInfo['out_numbers'];//单号
        $toparam['poitem'] = $WorkInModelInfo['out_rows'];//项次
        $toparam['create_time'] = time();

        $sql_where = [];
        $sql_where[] = ['recordid', '=', $RecordInId];
        $lid = $this->where($sql_where)->value('id');
        if (!$lid) {
            $lid = $this->insertGetId($toparam);
        }
        return $lid;
    }

    public function addRecordInLabes($param)
    {
        if (!$param['id']) {
            error(10032, '入库单据id为空');
        }
        $recordinfo = (new RecordInModel())->where('id', $param['id'])->find();
        $goodslabelstemplate = new GoodsLabelsTemplate();
        if (!$param['labelstempid']) {
            error(10032, '标签模板id为空');
        }
        $labelstp = $goodslabelstemplate->find($param['labelstempid']);


        //填充标签对应的数据
        $toparam['numbers'] = $this->setNumbers();
        $toparam['recordid'] = $recordinfo['id'];
        $toparam['record_type'] = 1;
        $toparam['goods_total'] = $recordinfo['num_plan'];//每托数量
        $toparam['receivingid'] = 0;
        $toparam['goodsid'] = $recordinfo['goodsid'];//基础物料ID
        $toparam['goods_batch'] = $recordinfo['goods_batch'] ?? date('Ymd'); //物料批次
        $toparam['goods_numbers'] = $recordinfo['goods_numbers']; //物料编号
        $toparam['goods_title'] = $recordinfo['goods_title']; //物料名称
        //$toparam['supplier_batch'] = $recordinfo['supplier_batch']; //供应商批次
        //$toparam['supplier_title'] = $recordinfo['supplier_title']; //供应商名称
        $toparam['goods_description'] = (new GoodsBase())->where('id', $recordinfo['goodsid'])->value('description');
        $toparam['processid'] = $labelstp['processid'];//业务流程
        $toparam['templateid'] = $labelstp['id'];//打印模板ID
        $toparam['pono'] = $recordinfo['out_numbers'];//单号
        $toparam['poitem'] = $recordinfo['out_rows'];//项次
        $toparam['create_time'] = time();

        $sql_where = [];
        $sql_where[] = ['recordid', '=', $recordinfo['id']];
        $lid = $this->where($sql_where)->value('id');
        if (!$lid) {
            $lid = $this->insertGetId($toparam);
        }
        return $lid;
    }

    /**
     * 将备料标记取消
     */
    public function setPdoOutNoByEmpty($pdooutno)
    {
        return $this->where('pdooutno', $pdooutno)->save(['pdooutno' => '']);
    }

    /**
     *
     * Created by PhpStorm.
     * User: chenyihang
     * Date: 2021/12/30
     * Time: 09:27
     *
     * 打印平库标签
     * 货架上面的1，取走的是2
     */
    public function printpklabel($param)
    {

        $deviceprinter = new DevicePrinter();
        $goodslabelstemp = new GoodsLabelsTemplate();
        $gtinfo = $goodslabelstemp->find($param['templabelsid']);

        $list = json_decode($param['labelsid'], true);
        if (!is_array($list)) {
            error(10032, 'labelsid非数组');
        }
        foreach ($list as $item) {
            $labelinfo = $this->find($item);
            if ($labelinfo['record_type'] == 1) {//入库
                $labelinfo['tagvo'] = 1;
            } elseif ($labelinfo['record_type'] == 2) {//出库
                $labelinfo['tagvo'] = 2;
            }
            $print_res = $deviceprinter->printer($param['deviceid'], $gtinfo['data'], $labelinfo);

            if ($print_res) {
                $data['print_count'] = $labelinfo['print_count'] + 1;
                $this->where('id', $item)->save($data);
            }
            unset($print_res);
        }

        return;
    }

    /**
     * 标记出库对标签进行标记，保持流水日志
     * @param $labelsid  标签id
     * @param $res   任务列表集合
     */
    public function liKusetCmdLogOutByLabelsStatus($labelsid, $res)
    {
        $log = [];
        $log['tray_numbers'] = $res['tray_numbers'];
        $log['recordid'] = $res['recordid'];
        $log['record_type'] = $res['record_type'];
        $log['cmdtype'] = $res['cmdtype'];
        $log['process_id'] = $res['process_id'];
        $log['tray_type'] = 0;
        $log['tray_size'] = 0;
        $log['create_time'] = time();
        $log['userid'] = 1;
        //标记标签
        $where1 = array();
        $where1[] = ['id', '=', $labelsid];
        $Label_list = $this->where($where1)->select();
        $goods_total = 0;
        foreach ($Label_list as $v) {
            $log['goods_numbers'] = $v['goods_numbers'];
            $log['goods_title'] = $v['goods_title'];
            $log['goods_batch'] = $v['goods_batch'];
            $log['goods_label'] = $v['numbers'];
            $log['templateid'] = $v['templateid'];
            $log['supplier_title'] = $v['supplier_title'];
            $log['supplier_batch'] = $v['supplier_batch'];
            $log['outdate_now'] = time();
            $log['indate'] = $v['indate'];
            $log['factory'] = $v['factory'];
            $log['storage'] = $v['storage'];
            $log['classes'] = $v['classes'];
            $log['workers'] = $v['workers'];
            $log['qtstatus'] = $v['qtstatus'];
            $log['ch_num'] = $v['goods_total'];
            $room3dinfo = (new Wareroom3d())->where('id', $v['wareid'])->find();
            $log['wareroom_zone'] = $room3dinfo['zone'];
            $log['wareroom_u'] = $room3dinfo['u'];
            $log['wareroom_x'] = $room3dinfo['x'];
            $log['wareroom_y'] = $room3dinfo['y'];
            $log['wareroom_z'] = $room3dinfo['z'];
            $log['create_time'] = time();
            $success = (new GoodsLog())->save($log);
            $success = $this->where('id', $v['id'])->save([
                'move_status' => 2,
                'record_type' => 2,
                'outdate_now' => time(),
                'create_time' => time(),
                'wareid' => 0,
                'trayid' => 0,
                'tray_numbers' => '',
                'pdooutno' => '']);
        }
    }

    /**
     * 获取四项车库过账列表
     * @param int $isposting 状态
     * @param int $field 自己控制字段
     * @return \think\Collection
     */
    public function getListInByPosting()
    {
        $sql_where = [];
        $sql_where[] = ['record_type', '=', 1];//入库才需要过账
        $sql_where[] = ['isposting', '=', 0];
        $sql_where[] = ['processid', 'in', [1, 2, 69, 77]];
        $sql_where[] = ['wareid', '>', 0];
        $sql_where[] = ['sdvono', 'like', 'SZHT%'];
        $record_list = $this->where($sql_where)->field('id')->select();
        var_dump($this->getLastSql());
        return $record_list;
    }

    //生成二维码 https://www.freesion.com/article/7789823231/
    public function qcode($id)
    {
        require dirname(__DIR__) . '/../vendor/phpqrcode/phpqrcode.php';
        $numbers = $this->where('id', $id)->value('numbers');
        if (!$numbers) {
            error(10032, '标签号为空');
        }
        $qRcode = new \QRcode();
        $data = $numbers;//网址或者是文本内容
        // 纠错级别：L、M、Q、H
        $level = 'L';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 4;
        // 生成的文件名
        $qRcode->png($data, false, $level, $size);
        $img = ob_get_contents();
        ob_end_clean();
        $imginfo = 'data:png;base64,' . chunk_split(base64_encode($img));//转base64
        return ['src' => $imginfo];
        return '<img src=' . $imginfo . '  />';
    }

    /**
     * 直接导出的是标签数据
     * @param $id
     * @return string|string[]
     * @throws 到处execl
     */
    public function execl($param)
    {
        if ($param['zone'] == 1 && !isset($param['u'])) {
            error(10032, '没有巷道u');
        }
        if (!isset($param['zone'])) {
            error(10032, '没有库区zone');
        }
        if ($param['zone'] == 1 || $param['zone'] == 2) {
            $where = [];
            if (is_array($param['u'])) {
                $where[] = ['wd.u', 'in', $param['u']];
            } else {
                $where[] = ['wd.u', '=', $param['u']];
            }
            $where[] = ['wd.zone', '=', $param['zone']];
            if (isset($param['goodsid']) && $param['goodsid'] > 0) {
                $where[] = ['tg.goodsid', '=', $param['goodsid']];
            }

            $where[] = ['tg.trayid', '>', 0];
            $where[] = ['tg.wareid', '>', 0];
            $list = (new TrayGoodsModel())->alias('tg')
                ->join('wareroom3d wd', 'wd.id = tg.wareid')
                ->join('tray t', 't.id = tg.trayid')
                ->join('goods_labels g', 'tg.labelsid = g.id')
                ->join('goods_base b', 'b.id = tg.goodsid')
                ->where($where)
                ->field('g.goods_numbers,g.goods_title,b.description,g.goods_batch,g.goods_total,g.labels_type,t.numbers,wd.u,wd.x,wd.y,wd.z,tg.indate')
                ->select();
            //var_dump((new TrayGoodsModel())->getLastSql());
            return $list;
        } elseif ($param['zone'] == 3) {
            $where = [];
            $where[] = ['tg.type', '=', 1];
            $where[] = ['wd.zone', '=', $param['zone']];
            if (isset($param['goodsid']) && $param['goodsid'] > 0) {
                $where[] = ['g.goodsid', '=', $param['goodsid']];
            }
            $where[] = ['tg.wareid', '>', 0];
            $list = (new TrayGoodsModel())->alias('tg')
                ->join('wareroom2d wd', 'wd.id = tg.wareid')
                ->join('goods_labels g', 'tg.labelsid = g.id')
                ->join('goods_base b', 'b.id = tg.goodsid')
                ->where($where)
                ->field('g.goods_numbers,g.goods_title,b.description,g.goods_batch,g.goods_total')
                ->select();
            //var_dump($this->getLastSql());
            return $list;
        }

    }

    /**
     * 直接导出物料号下不同数量的统计 的是标签数据
     * @param $id
     * @return string|string[]
     * @throws 到处execl
     */
    public function execlTotal($param)
    {
        if ($param['zone'] == 1 && !isset($param['u'])) {
            error(10032, '没有巷道u');
        }
        if (!isset($param['zone'])) {
            error(10032, '没有库区zone');
        }

        if ($param['zone'] == 1 || $param['zone'] == 2) {

            $where = [];
            if (is_array($param['u'])) {
                $where[] = ['wd.u', 'in', $param['u']];
            } else {
                $where[] = ['wd.u', '=', $param['u']];
            }
            $where[] = ['wd.zone', '=', $param['zone']];
            if (isset($param['goodsid']) && $param['goodsid'] > 0) {
                $where[] = ['tg.goodsid', '=', $param['goodsid']];
            }
            $where[] = ['tg.trayid', '>', 0];
            $where[] = ['tg.wareid', '>', 0];
            $list = (new TrayGoodsModel())->alias('tg')
                ->join('wareroom3d wd', 'wd.id = tg.wareid')
                ->join('goods_labels g', 'tg.labelsid = g.id')
                ->join('goods_base b', 'b.id = tg.goodsid')
                ->where($where)
                ->field('g.goods_numbers,g.goodsid,g.goods_title,b.description,g.goods_batch,g.goods_total')
                ->group('g.goodsid,g.goods_total')
                ->select()
                ->toArray();
            return $list;
        } elseif ($param['zone'] == 3) {
            $where = [];
            $where[] = ['tg.type', '=', 1];
            $where[] = ['wd.zone', '=', $param['zone']];
            if (isset($param['goodsid']) && $param['goodsid'] > 0) {
                $where[] = ['g.goodsid', '=', $param['goodsid']];
            }
            $where[] = ['tg.wareid', '>', 0];
            $list = (new TrayGoodsModel())->alias('tg')
                ->join('wareroom2d wd', 'wd.id = tg.wareid')
                ->join('goods_labels g', 'tg.labelsid = g.id')
                ->join('goods_base b', 'b.id = tg.goodsid')
                ->where($where)
                ->field('g.goods_numbers,g.goodsid,g.goods_title,b.description,g.goods_batch,g.goods_total')
                ->group('g.goodsid,g.goods_total')
                ->select()
                ->toArray();
            //var_dump($this->getLastSql());
            return $list;
        }

    }
}
