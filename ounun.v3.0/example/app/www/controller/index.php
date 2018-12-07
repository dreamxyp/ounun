<?php
/** 命名空间 */
namespace module;

use admin\purview;

class system extends \adm
{
    /** 开始init */
	public function index($mod)
	{
        // print_r(['$_SESSION'=>$_SESSION]);
        // exit();
		if (self::$auth->login_check())
		{
		    $this->_nav_set_data();
            $this->init_page('/');

            $db     = self::db ( 'adm' );
            $cid    = \adm::$auth->session_get(purview::s_cid);

            require $this->require_file('index.html.php');
		}else
		{
		    // 还没登录
            \ounun::go_url ('/login.html');
		}	
	}

	/** 登录Post */
	public function login($mod)
	{
		// ---------------------------------------
		// 登录
		if ($_POST)
		{
			$this->_login_post($_POST);
			exit();
		}
		// ---------------------------------------
		// 显示页面
		if ( self::$auth->login_check() )
		{
		    // 登录了
            \ounun::go_url ('/');
		}else
		{
		    // 还没登录
            $this->_nav_set_data();
            $this->init_page('/login.html');

            require $this->require_file('login.html.php');
		}
	}

	private function _login_post($args)
	{
        $rs = self::$auth->login($args['admin_username'],$args ['admin_password'],(int)$args['admin_cid'],$args ['admin_google']);
		if ($rs->ret )
		{
            // var_dump($_SESSION);
		    // var_dump($rs);
            \ounun::go_url ('/');
		}else
		{
			echo \ounun::msg ( $rs->data);

            \ounun::go_url ('/',false,302,2);
		}
	}

	/** 退出登录 */
	public function out($mod)
	{
        self::$auth->logout();
        \ounun::go_url('/login.html' );
	}

	/** 权限受限 */
	public function no_access($mod)
	{
        $this->_nav_set_data();
        $this->init_page('/no_access.html');

        //  echo $this->require_file('sys/no_access.html.php' );
        require $this->require_file('sys_adm/no_access.html.php' );
	}

	/** 提示 没有选择平台 与 服务器 */
	public function select_tip($mod)
	{
		$this->template();
		
		$nav  = (int)$_GET['nav'];
		if(1 == $nav &&  0 == self::$auth->session_get(purview::s_cid) )
		{
			$title_sub = '请选择“平台”';
		}
		elseif(2 == $nav && 0 == self::$auth->session_get(purview::s_cid)  )
		{
			$title_sub = '请选择“平台”与“服务器”';
		}elseif(2 == $nav)
		{
			$title_sub = '请选择“服务器”';
		}
        require $this->require_file( 'sys/select_tip.html.php' );
	}

	/** 设定当前平台 与服务器 */
	public function select_set($mod)
	{
		if(isset($_GET['cid']))
		{
		    self::$auth->cookie_set(purview::cp_cid,$_GET['cid']);
		}
		if (isset($_GET['sid']))
		{
            self::$auth->cookie_set(purview::cp_sid,$_GET['sid']);
		}
		if (isset($_GET['uri']))
		{
            \ounun::go_url($_GET['uri']);
		}
	}

	/** 显示认证码 */
	public function captcha($mod)
	{
		// \plugins\captcha\Captcha::output();
		\plugins\image\captcha::output();
	}
}