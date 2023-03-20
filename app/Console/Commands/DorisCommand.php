<?php

namespace App\Console\Commands;

use App\Models\MacroData;
use App\Models\MacroDataNew;
use Illuminate\Console\Command;

class DorisCommand extends Command
{

    /**
     * CREATE TABLE `macro_index_data4` (
    `rid` INT(11) NOT NULL,
    `index_code` VARCHAR(50) NULL COMMENT '指标名称',
    `occur_period` INT(11) NULL,
    `occur_period_t` VARCHAR(20) NULL COMMENT 'tableau专用数据期',
    `occur_period_year` INT(11) NULL COMMENT '年',
    `occur_period_month` INT(11) NULL COMMENT '月',
    `area_code` VARCHAR(32) NULL COMMENT '区划代码',
    `dim_cal` VARCHAR(32) NULL COMMENT '计算类型',
    `dim_dur` VARCHAR(32) NULL COMMENT '时间特征',
    `dim_extra` VARCHAR(32) NULL COMMENT '其他维度',
    `index_value` DOUBLE NULL COMMENT '绝对值',
    `unit_use` VARCHAR(50) NULL COMMENT '库中使用单位',
    `label` INT(11) NOT NULL COMMENT '数据属性（1代表官方公布宏观数据，2代表中观数据）',
    `source_code` VARCHAR(100) NULL COMMENT '数据来源',
    `update_time` DATETIME NULL COMMENT '更新时间',
    `area_code_type` INT(11) NULL COMMENT '区划代码类型（1-行政区 2-关区 3-片区 4-园区）'
    ) ENGINE=OLAP
    UNIQUE KEY(`rid`)
    COMMENT '宏观指标数据表'
    DISTRIBUTED BY HASH(`rid`) BUCKETS 32
    PROPERTIES (
    "replication_allocation" = "tag.location.default: 1",
    "in_memory" = "false",
    "storage_format" = "V2",
    "disable_auto_compaction" = "false"
    );

     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doris:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $id = 1;
        while (true){
            $data =  MacroData::where('rid', '>', $id)->orderBy('rid')->limit(2000)->get();
            if (empty($data)){
                echo '执行完成';
                break;
            }
            $data = $data->toArray();
            MacroDataNew::insert($data);

            $id  = end($data)['rid'];
            echo '--'.$id.'-\n-';
        }
        return 0;
    }
}
