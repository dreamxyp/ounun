<?php require v::tpl_fixed('_head.html.php')?>
<div class="container" id="cpcontainer">
    <div class="floattopempty"></div>
    <table class="tb tb2 " id="tips">
        <tr>
            <th class="partition">你好, <?php echo \adm::$auth->session_get(\model\purview::s_account);?> <span style="color: red;">{$page_title_sub} </span>！</th>
        </tr>
    </table>
</div>
<?php require v::tpl_fixed('_foot.html.php')?>