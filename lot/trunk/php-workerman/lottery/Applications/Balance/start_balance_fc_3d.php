<?php

ini_set("memory_limit", "-1");

use \Workerman\Worker;
use \Workerman\Lib\Timer;
use \Workerman\Connection\AsyncTcpConnection;
use \helper\MysqlPdo as pdo;
use \helper\RedisConPool;
use \helper\Common_helper;
use \helper\Curl;

require_once __DIR__ . '/../../Workerman/Autoloader.php';

Worker::$stdoutFile = __DIR__ . '/../../logs/worker.log';

$worker = new Worker('http://0.0.0.0:8694');
$worker->name = 'fc_3dbalanceWorker';
$worker->onWorkerStart = function($worker) {
    $typelist = [
        'fc_3d',
        'pl_3'
    ];

    // ///读出往期未结算注单    此功能暂不使用
    // foreach ($typelist as $type) {
    //     $class = '\libraries\balance\\' . ucfirst($type) . 'Balance';                            ///结算类
    //     $qishu_list = Common_helper::getNumberOnStart($type);              ///获取以往未结算期数 返回以期数为键1为值的数组（不包含本期）
    //     if ($qishu_list) {
    //         foreach ($qishu_list as $qishu => $val) {   //根据彩种期数获取未结算注单
    //             $class::$bets[$qishu] = Common_helper::getAllByNumber($type, $qishu);
    //             $class::$run[$qishu] = 1;                              ////加载注单就绪
    //             // echo 'number '.$qishu.' isnot balanced'.PHP_EOL; //未结算的期数
    //             Common_helper::BalanceByType($type, $qishu); //根据type和期数 进入结算
    //         }
    //     }

    //     ///当前期数
    //     Common_helper::LoadAllBets($class, $type);
    // }

    Timer::add(10, function()use($typelist)               ///定期将下期数据缓冲到内存
    {
        foreach ($typelist as $type) {
            $class = '\libraries\balance\\' . ucfirst($type) . 'Balance';                      ///结算类
            Common_helper::LoadAllBets($class, $type);
        }
    });
};


$worker->onMessage = function($connection, $data) {

    if (!isset($_POST['todo'])) {                     ///无指定命令
        $connection->send("invaild commond");
        return;
    }

    if (!isset($_POST['type'])) {                     ///无指定彩种
        $connection->send("invaild type");
        return;
    }

    $class = '\libraries\balance\\' . ucfirst($_POST['type']) . 'Balance'; //结算类                           

    if($_POST['todo'] == 'count'){ //获取内存中的注单条数
        $data = Common_helper::getMemoryCount($class);
        $connection->send($data);
        return;
    }

    $type = $_POST['type'];
    $qishu = $_POST['qishu'];
    switch ($_POST['todo']) {
        case 'get':                                 ///查询指令
            if (isset($_POST['qishu'])) {
                $data = Common_helper::getDataFromMemory($class, $_POST['qishu']);
                $connection->send($data);
            } else {
                $connection->send(null);
            }

            return;
            break;

        case 'balance'://补结算（用于人工输入开奖结果时 进行结算）
            if (!isset($_POST['qishu'])) {                        ///期数
                $connection->send("invaild qishu");
                return;
            }
            $redis_key = Common_helper::GetQiShuRedisKeyByType($type); //存放期数
            $redis = RedisConPool::getInstace();
            if ($redis->EXISTS($redis_key)) {                    ////比对期数
                $content = trim($redis->get($redis_key));
                if ($_POST['qishu'] == $content) {
                    $connection->send("repeated qishu");
                    return;
                }
            }

            break;
    }

    if (!isset($class::$run[$qishu])) {
        // echo 'Have no betList' . PHP_EOL;
        $connection->send("this server qishu noisset");
        return;
    }

    Common_helper::BalanceByType($type, $qishu);
    $connection->send("start");
    return;
};


// 运行worker
if(!defined('GLOBAL_START')) {
    Worker::runAll();
}
?>