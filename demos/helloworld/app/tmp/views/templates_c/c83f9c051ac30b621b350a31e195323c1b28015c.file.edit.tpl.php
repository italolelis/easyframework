<?php /* Smarty version Smarty-3.1.7, created on 2012-03-26 02:26:36
         compiled from "C:\xampp\htdocs\easyframework\demos\helloworld\app\View\Pages\Users\edit.tpl" */ ?>
<?php /*%%SmartyHeaderCode:236904f6ffe0c07a8d8-66200928%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    'c83f9c051ac30b621b350a31e195323c1b28015c' => 
    array (
      0 => 'C:\\xampp\\htdocs\\easyframework\\demos\\helloworld\\app\\View\\Pages\\Users\\edit.tpl',
      1 => 1332739394,
      2 => 'file',
    ),
    '8a23679ac89488230e9c466fbb23e6a76bb8e639' => 
    array (
      0 => 'C:\\xampp\\htdocs\\easyframework\\demos\\helloworld\\app\\View\\Layouts\\layout.tpl',
      1 => 1332739394,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '236904f6ffe0c07a8d8-66200928',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'layout' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.7',
  'unifunc' => 'content_4f6ffe0c0d774',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_4f6ffe0c0d774')) {function content_4f6ffe0c0d774($_smarty_tpl) {?><!DOCTYPE html>
<html lang="pt-BR"> 
    <head>
        <meta charset="utf-8">
        <title>Exemplo Hello World</title>
    </head>
    <body>
        
<h1>Edit User <?php echo $_smarty_tpl->tpl_vars['user']->value->username;?>
</h1>
<form method="POST" action="<?php echo $_smarty_tpl->tpl_vars['url']->value['editUser'];?>
/<?php echo $_smarty_tpl->tpl_vars['user']->value->id;?>
">
    <label>Nome: </label><input type="text" name="username" value="<?php echo $_smarty_tpl->tpl_vars['user']->value->username;?>
"/>
    <button type="submit">Confirmar</button>
</form>
<hr/>
<button onclick="location.href='<?php echo $_smarty_tpl->tpl_vars['url']->value['users'];?>
'">Back to users</button>

    </body>
</html><?php }} ?>