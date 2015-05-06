<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * Light-AudioPlayer For Typecho
 * 
 * @package Light-AudioPlayer
 * @author Mike
 * @version 1.0
 * @link http://www.microhu.com
 */
class LightAudioPlayer_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->filter = array('LightAudioPlayer_Plugin','playerfilter');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('LightAudioPlayer_Plugin','playerparse');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('LightAudioPlayer_Plugin','playerparse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('LightAudioPlayer_Plugin','playercss');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('LightAudioPlayer_Plugin','playerjs');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $options = Helper::options();
        echo '
        <style>
        code{background: #EF8D8D;color: #FFF;margin: 5px;}
        body{font-family:"Microsoft Yahei"}
        .typecho-page-title{display:none}
        .main{margin-top:60px}
        .la-header{font-size:36px;margin-bottom:5px;}
        </style>

        <div class="la-header">欢迎使用Light-AudioPlayer For Typecho</div>
        <div class="la-content">
        <p>来自本人开源项目：<a href="https://github.com/mikeyzm/Light-AudioPlayer" target="_blank">Light-AudioPlayer</a>
        <p>使用说明：在文章内输入<code>[mp3]文件地址[/mp3]</code>即可</p>
        <!--<p>可附带参数<code>preload,autoplay,loop</code><a href="https://github.com/mikeyzm/Light-AudioPlayer/blob/master/README.md" target="_blank">详细说明</a></p>-->
        </div>
        ';

        $MP3Address = new Typecho_Widget_Helper_Form_Element_Checkbox('MP3Address',
        array('1'=>_t('将文章内地址为MP3的链接替换成播放器')),NULL,_t('MP3地址替换'));
        $form->addInput($MP3Address);

        $jQuery = new Typecho_Widget_Helper_Form_Element_Checkbox('jQuery',
        array('1'=>_t('载入最新版本的jQuery')),NULL,_t('jQuery依赖'));
        $form->addInput($jQuery);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 头部CSS挂载
     * 
     * @return void
     */
    public static function playercss()
    {
        $playercssurl = Helper::options()->pluginUrl.'/LightAudioPlayer/player/css/';
        echo '
        <!-- LightAudioPlayer Style -->
        <link rel="stylesheet" href="'.$playercssurl.'audioplayer.css" />
        ';
    }

    /**
     * 尾部JS挂载
     * 
     * @return void
     */
    public static function playerjs()
    {
        $playerjsurl = Helper::options()->pluginUrl.'/LightAudioPlayer/player/js/';
        if (Typecho_Widget::widget('Widget_Options')->plugin('LightAudioPlayer')->jQuery) {
            echo '<script type="text/javascript" src="'.$playerjsurl.'jquery.min.js"></script>';
        }
        echo '
        <!-- LightAudioPlayer JavaScript -->
        <script type="text/javascript" src="'.$playerjsurl.'audioplayer.js"></script>
        ';
        echo "<script>$( function() { $( 'audio' ).audioPlayer(); } );</script>";
    }

    /**
     * MD兼容性过滤
     * 
     * @param array $value
     * @return array
     */
    public static function playerfilter($value)
    {
        //屏蔽自动链接
        if ($value['isMarkdown']) {
            $value['text'] = preg_replace('/(?!<div>)\[(mp3)](.*?)\[\/\\1](?!<\/div>)/is','<div>[mp3]\\2[/mp3]</div>',$value['text']);
            //兼容JWPlayer
            $value['text'] = preg_replace('/(?!<div>)<(jw)>(.*?)<\/\\1>(?!<\/div>)/is','<div><jw>\\2</jw></div>',$value['text']);
        }
        return $value;
    }

    /**
     * 内容标签替换
     * 
     * @param string $content
     * @return string
     */
    public static function playerparse($content,$widget,$lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        $settings = Helper::options()->plugin('LightAudioPlayer');

        if ($widget instanceof Widget_Archive) {
            //替换mp3链接
            if ($settings->MP3Address) {
                $pattern = '/<a ([^=]+=[\'"][^"\']*[\'"] )*href=[\'"](([^"\']+\.mp3))[\'"]( [^=]+=[\'"][^"\']*[\'"])*>([^<]+)<\/a>/is';
                $content = preg_replace_callback($pattern,array('LightAudioPlayer_Plugin','parseCallback'),$content);
            }
            $content = preg_replace_callback('/\[(mp3)](.*?)\[\/\\1]/si',array('LightAudioPlayer_Plugin','parseCallback'),$content);
        }

        return $content;
    }

    /**
     * 参数回调解析
     * 
     * @param array $matches
     * @return string
     */
    public static function parseCallback($matches)
    {
        $atts = explode('|',$matches[2]);
        
        //分离参数
        $files = array_shift($atts);
        $data = array();

        foreach ($atts as $att) {
            $pair = explode('=',$att);
            $data[trim($pair[0])] = trim($pair[1]);
        }

        return self::getPlayer($files,$data);
    }

    /**
     * 输出播放器实例
     * 
     * @param string $source
     * @param array $playerOptions
     * @return string
     */
    public static function getPlayer($source,$playerOptions = array())
    {
        $settings = Helper::options()->plugin('LightAudioPlayer');

        //文件地址
        if (function_exists('html_entity_decode')) {
            $source = html_entity_decode($source);
        }

        //生成实例
        $playerCode = '<audio src="'.$source.'" preload="auto" controls></audio>';

        return $playerCode;
    }

}
