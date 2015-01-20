<?php
/**
 * Plugin Name: 2kb Performance
 * Plugin URI: http://www.2kblater.com
 * Description: Ultimate Performance Boost For Your Site. Merge and cache css/javascript files and reduce server request up to 90%.
 * Version: 1.1.0
 * Author: 2kblater.com
 * Author URI: http://www.2kblater.com
 * License: GPL2
 */

!defined('ABSPATH') and exit;


define('KbPerformanceVersion', '1.0.0');
define('KbPerformanceFolderName', pathinfo(dirname(__FILE__), PATHINFO_FILENAME));
define('KbPerformancePluginPath', dirname(__FILE__) . '/');


function getKbPerformance()
{
    static $instance;
    if (!$instance) {
        $instance = new kbPerformance;
    }
    return $instance;
}
getKbPerformance();

class kbPerformance
{
    const CSS_FILE_NAME = '2kb-performance-cached.css';
    const CSS_OPTION    = '2kb-performance-cached-css';
    
    const JS_FILE_HEAD  = '2kb-performance-cached-head.js';
    const JS_FILE_FOOTER  = '2kb-performance-cached-footer.js';
    const JS_OPTION     = '2kb-performance-cached-js';
    
    const NOTICE_CHECK_OPTION     = '2kb-performance-notice-check';
    
    public $reload = false;
    
    public $uploadsPath;
    public $uploadsUrl;
    
    protected $jsSrcData = array();
    protected $cssSrcData = array();

    /**
     * RELOAD ON EVENTS
     */
    
    protected $reploadOn = array(
        'deactivated_plugin',
        'activated_plugin',
        'after_switch_theme'
    );

    public function __construct()
    {
        $uploadDir = wp_upload_dir();
        $this->uploadsPath = $uploadDir['basedir'] . '/' . KbPerformanceFolderName;
        $this->uploadsUrl  = $uploadDir['baseurl'] . '/' . KbPerformanceFolderName;

        if (!is_admin() && !isset($_GET['2kb-performance-cache'])) {
            $this->removeScripts();
            add_action('init', array($this, 'initCss'), 1);
            add_action('init', array($this, 'initJsHead'), 1);
            add_action('wp_footer', array($this, 'initJsFooter'), 99999);
        }
        
        if (is_admin()) {
            add_action('admin_menu', array($this, 'addAdminMenu'));
            foreach ($this->reploadOn as $event) {
                add_action($event, array($this, 'generateCacheFiles'));
            }
            /**
             * CACHE DIR
             */
            if (!file_exists($this->uploadsPath)) {
                wp_mkdir_p($this->uploadsPath);
            }
        }
        if (isset($_GET['2kb-performance-cache'])) {
            add_filter('2kb-performance-css-content-filter', array($this, 'filterCssContent'), 1 , 2);
            add_filter('2kb-performance-js-content-filter', array($this, 'filterJsContent'), 1 , 2);
            add_filter('script_loader_src', array($this, 'kbCacheScriptSrc'), 1 , 2);
            add_filter('style_loader_tag', array($this, 'kbCacheStyleSrc'), 1, 2);
            add_action('shutdown' ,array($this, 'kbCacheScripts'), 99999);
            add_action('shutdown', array($this, 'kbCacheStyles'), 99999);
        }    
        
        if (isset($_GET['page']) && $_GET['page'] == '2kb-performance') {
            add_action('admin_notices', array($this, 'pluginNotise'));
        } else {
            $this->addNotiseCheck(true);
        }
        
        /**
         * CRON
         */
        if (!wp_next_scheduled('2kb-performance-cron')) {
            wp_schedule_event(time(), 'daily', '2kb-performance-cron');
        }
        add_action('2kb-performance-cron', array($this, 'generateCacheFiles'));
    }
    
    public function addNotiseCheck($load = false)
    {
        $js  = $this->getJsCachedFile();
        $css = $this->getCssCachedFile();
        if ($load && (!$js || !$css)) {
            add_action('admin_notices', array($this, 'addNotiseCheck'));
        } else if ($load == false) {
            ?>
            <div class="error">
                <p>
                    2kb-performance: generate your cache files from <a href="<?php echo get_admin_url();?>options-general.php?page=2kb-performance">here</a>.
                </p>
            </div>
            <?php
        }
    }

    public function clearOptions()
    {
        update_option(self::CSS_OPTION, null);
        update_option(self::JS_OPTION, null);
    }

    public function removeScripts()
    {
        $css = $this->getCssCachedFile();
        if ($css) {
            add_filter('style_loader_tag', array($this, 'styleLoaderTagRemove'), 1 , 2);
        }
        $js = $this->getJsCachedFile();
        if ($js) {
            add_filter('script_loader_src', array($this, 'scriptLoaderTagRemove'), 1 , 2);
        }
    }

    public function initCss()
    {
        $css = $this->getCssCachedFile();
        if ($css) {
            wp_enqueue_style(
                self::CSS_FILE_NAME,
                $css['url'],
                array(),
                KbPerformanceVersion,
                'all'
            );
            
        }
    }
    
    public function initJsHead()
    {
        $js = $this->getJsCachedFile();
        if ($js) {
            wp_enqueue_script(
                self::JS_FILE_HEAD,
                $js[self::JS_FILE_HEAD]['url']
            );
        }
    }

    public function initJsFooter()
    {
        $js = $this->getJsCachedFile();
        if ($js) {
            $js = $js[self::JS_FILE_FOOTER];
            echo "\n";
            ?>
<script type="text/javascript" src="<?php echo $js['url'];?>"></script>
            <?php
        }
    }

    public function kbCacheStyles()
    {
        global $wp_styles;
        $content = '';
        $contentCondition = '';
        $css = array();
        foreach ($this->cssSrcData as $cssData) {
            $handle = $cssData['handle'];
            $obj = $wp_styles->registered[$handle];
            $src = $this->prepareUrl($obj->src);
            $media = isset($obj->args) ? esc_attr($obj->args) : 'all';
            $media = $media == 'all' || $media == 'screen' ? 'all' : $media;
            $condition = isset($obj->extra['conditional']) ? $obj->extra['conditional'] : null;
            if ($condition) {
                continue;
            }
            $cssRow = array(
                'handle' => $handle,
                'url' => $src,
                'media' => $media
            );
            $data = $this->fileGetContents($src);
            if (empty($data)) {
                continue;
            }
            $contentStr = apply_filters(
                '2kb-performance-css-content-filter',
                $data,
                $cssRow
            );
            if ($media == 'all') {
                $content .= $contentStr;
            } else {
                $contentCondition .= $contentStr;
            }
            $css[] = $cssRow;
        }
        $content = $content . "\n /***2kb-performance conditional css***/ \n" . $contentCondition;
        $cssOption = null;
        if (!empty($content)) {
            
            try {
                require_once KbPerformancePluginPath . 'lib/YUI-CSS-compressor-PHP/cssmin.php';
                $compressor = new CSSmin();
                $content = $compressor->run($content);
            } catch (Exception $e) {

            }
            
            $file = $this->uploadsPath . '/' . self::CSS_FILE_NAME;
            $this->filePutContents(
                $file,
                $content
            );
            if (file_exists($file)) {
                $cssOption = array(
                    'date' => date('Y-m-d H:i:s'),
                    'url' => $this->uploadsUrl . '/' . self::CSS_FILE_NAME,
                    'name' => self::CSS_FILE_NAME,
                    'size' => $this->formatSizeUnits(filesize($file)),
                    'css' => $css
                );
            }
        }
        update_option(self::CSS_OPTION, $cssOption);
    }
    
    public function kbCacheScripts()
    {
        $option = null;
        if (!empty($this->jsSrcData)) {
            $contentHead = '';
            $contentFooter = '';
            $jsData[self::JS_FILE_HEAD] = array();
            $jsData[self::JS_FILE_FOOTER] = array();
            foreach ($this->jsSrcData as $js) {
                $data = $this->fileGetContents($js['url']);
                if (empty($data)) {
                    continue;
                }
                $contentData = apply_filters(
                    '2kb-performance-js-content-filter',
                    $data,
                    $js
                );
                
                if (isset($js['ob']->extra['group']) && $js['ob']->extra['group'] == 1) {
                    $contentFooter .= $contentData;
                    $jsData[self::JS_FILE_FOOTER][] = array(
                        'url' => $js['url'],
                        'handle' => $js['handle']
                    );
                } else {
                    $contentHead .= $contentData;
                    $jsData[self::JS_FILE_HEAD][] = array(
                        'url' => $js['url'],
                        'handle' => $js['handle']
                    );
                }
            }
            $option[self::JS_FILE_HEAD] = null;
            $option[self::JS_FILE_FOOTER] = null;
            foreach (array(self::JS_FILE_HEAD => $contentHead, self::JS_FILE_FOOTER => $contentFooter) as $key => $content) {
                if (!empty($content)) {
                    $file = $this->uploadsPath . '/' . $key;
                    $this->filePutContents(
                        $file,
                        $content
                    );
                    if (file_exists($file)) {
                        $option[$key] = array(
                            'date' => date('Y-m-d H:i:s'),
                            'url' => $this->uploadsUrl . '/' . $key,
                            'name' => $key,
                            'size' => $this->formatSizeUnits(filesize($file)),
                            'js' => $jsData[$key]
                        );
                    }
                }
            }
        }
        update_option(self::JS_OPTION, $option);
    }
    
    function kbCacheScriptSrc($src, $handle)
    {
        /**
         * @var WP_Scripts
         */
        global $wp_scripts;
        $ob = $wp_scripts->registered[$handle];
        $url = $this->prepareUrl($src);
        if ($this->canUseSrc($url) && $handle != self::JS_FILE_HEAD && $handle != self::JS_FILE_FOOTER) {
            $this->jsSrcData[] = array(
                'url' => $url,
                'handle' => $handle,
                'ob' => $ob
            );  
        }
    }
   function kbCacheStyleSrc($str, $handle)
    {
       $str = str_replace("'", '"', $str);
       $url = $this->getBetween($str, 'href="', '"');
       if ($this->canUseSrc($url) && $handle != self::CSS_FILE_NAME) {
            $this->cssSrcData[] = array(
                'handle' => $handle,
                'url' => $url
            );
       }

       return $str;
   }
    
    
    public function prepareUrl($url)
    {
        if (substr($url, 0, 2) == '//') {
            $url = 'http://' . substr($url, 2);
        } else if (substr($url, 0, 1) == '/') {
            $url =  get_site_url() . $url;
        }
        return $url;
    }


    public function canUseSrc($src)
    {
        return strpos($src, $this->getHostname()) !== false;
    }
    
    public function addAdminMenu()
    {
        add_options_page('2kb Performance', '2kb Performance', 'manage_options', '2kb-performance', array($this, 'admin'));
    }
    
    function pluginNotise()
    {
        ?>
        <div class="updated">
            <p>
                2kb-performance will cache your css and javascript files and separate them in the header or footer. Moreover css compression will be applied via YUI-CSS-compressor-PHP.
                <br/>
                Every day Cron Job or plugin activate/disable will renew your 2kb-performance files.
            </p>
        </div>
        <?php
    }
    
    public function admin()
    {
        if (isset($_POST['action'])) {
            call_user_func_array(array($this, $_POST['action']), array());
        }
        $this->cssCachedFile    = $this->getCssCachedFile();
        $this->jsCachedFile     = $this->getJsCachedFile();

        include KbPerformancePluginPath . 'admin-template.php';
    }
    
    public function getCssCachedFile()
    {
        return get_option(self::CSS_OPTION, null);
    }
    
    public function getJsCachedFile()
    {
        return get_option(self::JS_OPTION, null);
    }

    public function generateCacheFiles()
    {
        $this->reload = true;
        $this->clearOptions();
        $this->fileGetContents(get_site_url() . '?2kb-performance-cache=1');
    }
    
    /**
     * Make css content cross browser accessable
     * @var $css [url, link]
     */
    public function filterCssContent($string, $css)
    {
        $url = $css['url'];
        $parts = parse_url($url);
        $pathParts = explode('/', $parts['path']);
        unset($pathParts[count($pathParts) - 1]);
        $parts['scheme'] = isset($parts['scheme']) ? $parts['scheme'] : 'http';
        $baseCssUrl = sprintf(
            '%s://%s%s/',
            $parts['scheme'],
            $parts['host'],
            implode('/', $pathParts)
        );
        
        $matches = array();
        $pattern = "/url\((.*?)\)/";
        preg_match_all($pattern, $string, $matches);
        $search     = array();
        $replace    = array();
        if (isset($matches[1]) && !empty($matches[1])) {
            foreach ($matches[1] as $url) {
                $search[] = $url;
                $replace[] = $baseCssUrl . str_replace(array('"', "'"), '', $url);
            }
        }
        $string = str_replace($search, $replace, $string);
        if ($css['media'] && $css['media'] != 'all') {
            $media = $css['media'];
            $string = "
                \n
                @media $media { \n
                    $string \n
                };\n
            ";
        }
        return $string;
    }
    
    public function filterJsContent($string, $js)
    {
        $url = $js['url'];
        $string = "
\n
/*\n 2kb-performance cached:\n $url \n*/ \n
" . $string;
        if (isset($js['ob']->extra['data']) && !empty($js['ob']->extra['data'])) {
            $string = "\n" . $js['ob']->extra['data'] . "\n;\n" . $string;
        }
        
        $string .= "\n;";
        return $string;
    }

    public function styleLoaderTagRemove($str, $handle)
    {
        if ($this->hasCssHandle($handle)) {
            $str = "<!--\n2kb-performance cached:\n$str -->\n";
        }
        return $str;
    }
    
    public function scriptLoaderTagRemove($src, $handle)
    {
        if ($this->hasJsUrl($src)) {
            return false;
        }
        return $src;
    }


    public function hasCssHandle($handle)
    {
        $css = $this->getCssCachedFile();
        if ($css) {
            foreach ($css['css'] as $d) {
                if ($d['handle'] == $handle) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function hasJsHandle($handle)
    {
        $js = $this->getJsCachedFile();
        if ($js) {
            foreach ($js as $name => $jsData) {
                foreach ($jsData['js'] as $d) {
                    if ($d['handle'] == $handle) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    public function hasJsUrl($url)
    {
        $url = $this->prepareUrl($url);
        $js = $this->getJsCachedFile();
        if ($js) {
            foreach ($js as $name => $jsData) {
                foreach ($jsData['js'] as $d) {
                    if ($this->prepareUrl($d['url']) == $url) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function fileGetContents($url)
    {
        $url .= (strpos($url, '?') === false ? '?' : '&') . '2kb-performance-request=1';
        $data = wp_remote_get($url);
        return is_array($data)
               && isset($data['body'])
               && !empty($data['body'])
               ? $data['body'] : false;
    }
    
    protected function filePutContents($name, $data)
    {
        $handle = fopen($name, 'w') or die('Cannot open file:  '.$name);
        fwrite($handle, $data);
        fclose($handle);
    }
    
    protected function getHostname()
    {
        if (!isset($this->hostName)) {
            $parts = parse_url(get_site_url());
            $this->hostName = str_replace('www.', '', $parts['host']);
        }
        return $this->hostName;
    }
        
    function getKbPluginUrl($append = null)
    {
        return get_site_url() . '/wp-content/plugins/' . KbPerformanceFolderName . ($append ? '/' : '') . $append;
    }
    
    function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }

    protected function getBetween($content, $start, $end)
    {
        $r = explode($start, $content);
        if (isset($r[1])){
            $r = explode($end, $r[1]);
            return $r[0];
        }
        return null;
    }
}

