<?php

namespace ounun\cmd\task\site_base;

use ounun\cmd\task\libs\com_baidu;
use ounun\cmd\task\manage;
use ounun\cmd\task\struct;

abstract class baidu_wap extends _site
{
    /**
     * baidu_wap constructor.
     * @param struct $task_struct
     * @param string $tag
     * @param string $tag_sub
     */
    public function __construct(struct $task_struct, string $tag = '', string $tag_sub = '')
    {
        $this->_tag = 'baidu_wap';
        $this->_tag_sub = '';
        parent::__construct($task_struct, $tag, $tag_sub);
    }

    /**
     * 执行任务
     * @param array $input
     * @param int $mode
     * @param bool $is_pass_check
     */
    public function execute(array $input = [], int $mode = manage::Mode_Dateup, bool $is_pass_check = false)
    {
        try {
            $this->_logs_state = manage::Logs_Succeed;
            // $this->url_push_baidu_pc_mip();
            $this->msg("Successful push baidu_wap");
        } catch (\Exception $e) {
            $this->_logs_state = manage::Logs_Fail;
            $this->msg($e->getMessage());
            $this->msg("Fail push baidu_wap");
        }
    }

    public function push_wap(array $urls)
    {
        $api = str_replace(['{$site}', '{$token}'], [$this->_url_root_wap, $this->_token_site], com_baidu::api_baidu_wap);
        return $this->_push($api, $urls);
    }

    /**
     * 定时  数据接口提交 mip
     * @param bool $is_today false :历史   true  :当天
     */
    public function do_push_wap($is_today = false)
    {
        $this->_push_step = com_baidu::max_push_step;
        do {
            $do = $this->_do_push(com_baidu::type_baidu_wap, $is_today);
        } while ($do);
    }
}
