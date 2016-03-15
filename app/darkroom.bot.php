<?php
    $config = [];
    $config['debug'] =  true;
    $config['maxImageWidthHeight'] = 2000;
    $config['imagePath'] = 'images/';
    $config['cachePath'] = '_cache/';
    $config['defaultCollection'] = 'random';

    ///////////////////////////////////////////////////////////////////////////
    // 0. setup document
    ///////////////////////////////////////////////////////////////////////////

    if ($config['debug'] === true){
        // enable all errors
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    // get requested path, if not specified
    // assume index path is requested
    $defaultPath = 'index.html';
    $path = $defaultPath;

    if (isset($_GET['path']) && $_GET['path'] !== ''){
        $path = $_GET['path'];
    }
    elseif (isset($_SERVER['REQUEST_URI']) && strlen($_SERVER['REQUEST_URI']) >= 1 ){
        $path = strtok($_SERVER['REQUEST_URI'], '?');
    }
    
    // remove slash if first character in path
    if ( substr ($path, 0, 1) == '/' ){
        $path = substr($path, 1);
    }

    // reset empty paths to default path
    if ($path === false || $path === ''){
        $path = $defaultPath;
    }

    // test if user is on index page, if so, show homepage, stop executing
    if ($path === $defaultPath){
        header('Content-Type: text/html' );
        header('Content-Length: ' . filesize($path));
        readfile($path);
        die();
    }

    // basic caching functions
    $apc_available = !$config['debug'] && extension_loaded('apc') && ini_get('apc.enabled') ? true : false;

    function cache_get($key, $default = null){
        global $apc_available;
        $result = $default;

        if ($apc_available && apc_exists($key)){
            $result = apc_fetch($key);
        }

        return $result;
    }

    function cache_set($key, $value){
        global $apc_available;
        if ($apc_available){
            // store 5 minutes by default
            apc_store($key, $value, 60 * 5);
        }
    }

    $collectionMap = false;
    function &collectionMap(){
        global $collectionMap, $config;

        if ($collectionMap === false){
            $collectionMap = cache_get('collection-map');

            if ($collectionMap === null){
                $collectionNames = array_filter(scandir($config['imagePath']), function($directoryItem){
                    global $config;
                    return $directoryItem !== '.' && $directoryItem !== '..' && is_dir($config['imagePath'].$directoryItem);
                });

                $collectionMap = [];
                foreach ($collectionNames as $collectionName){
                    $collectionMap[$collectionName] = false;
                }
                cache_set('collection-map', $collectionMap);
            }
        }

        return $collectionMap;
    }


    // Map of all images available
    function imageMap($collectionName){
        global $config;
        $collectionMap = &collectionMap();

        if ( !isset($collectionMap[$collectionName]) ){
            return false;
        }


        // if global image map is not set, get from cache
        $imageMap = $collectionMap[$collectionName];
        if ($imageMap === false){
            $imageMapCacheKey = 'image-map-' . $collectionName;
            $imageMap = cache_get($imageMapCacheKey);

            // if image map not in cache, generate map
            if ($imageMap === null){
                $imageMap = [];

                // collect all image files in images folder
                $imageFiles = array_filter(scandir($config['imagePath'].$collectionName.'/'), function($imageFile) use($config, $collectionName){
                    return @is_array(getimagesize($config['imagePath'].$collectionName.'/'.$imageFile));
                });

                // map each file under file name without extension
                foreach($imageFiles as $imageFile){
                    $imageKey = preg_replace('/\.[^.]+$/','',$imageFile);
                    $imageKey = strtolower($imageKey);
                    $imageMap[$imageKey] = $imageFile;
                }

                if (count($imageMap) === 0){
                    die('no images found');
                }
                
                $collectionMap[$collectionName] = $imageMap;
                cache_set($imageMapCacheKey, $imageMap);
            }
        }

        return $imageMap;
    }


    // flatten an sort an array to create a cacheable key
    function flatten_cacheable_array($inputArray){
        $outputArray = [];
        foreach ($inputArray as $key => $value) {
            $outputArray[] = (string)$key . '_-_' . (string)$value;
        }
        sort($outputArray);
        return implode($outputArray, '-_---');
    }



    ///////////////////////////////////////////////////////////////////////////
    // 1. compile configuration from url
    ///////////////////////////////////////////////////////////////////////////

    // configuration defaults

    $imageUrlCacheKey = 'image-url-'.$path.'-'.flatten_cacheable_array($_GET);
    $imageConfiguration = cache_get($imageUrlCacheKey);

    if ($imageConfiguration === null){
        $imageConfiguration = [];
        $imageConfiguration['width'] = 'auto';
        $imageConfiguration['height'] = 'auto';
        $imageConfiguration['image'] = 'random';
        $imageConfiguration['image'] = $config['defaultCollection'];


        // get width & height from path
        $pathParts = explode('/', $path);

        // collect width
        if ( $pathParts[0] !== 'auto' && is_numeric($pathParts[0]) ){
            // get numeric value, make sure its within allowed image width/height
            $imageConfiguration['width'] = (int)$pathParts[0];
        }

        // collect height
        if ( count($pathParts) > 1 && $pathParts[1] !== 'auto' && is_numeric($pathParts[1]) ){
            // get numeric value, make sure its within allowed image width/height
            $imageConfiguration['height'] = (int)$pathParts[1];
        }


        if (isset($_GET['collection'])){
            $imageConfiguration['collection'] = $_GET['collection'];
        }


        if (isset($_GET['image'])){
            $imageConfiguration['image'] = $_GET['image'];
        }

        // write to cache for future profit
        cache_set($imageUrlCacheKey, $imageConfiguration);
    }


    ///////////////////////////////////////////////////////////////////////////
    // 2. validate configuration
    ///////////////////////////////////////////////////////////////////////////
    

    // make sure given width is between width and max width
    if ( is_int($imageConfiguration['width']) ){
        // get numeric value, make sure its within allowed image width/height
        $imageConfiguration['width'] = min(max($imageConfiguration['width'], 1), $config['maxImageWidthHeight']);
    }

    // make sure given height is between height and max height
    if ( is_int($imageConfiguration['height']) ){
        // get numeric value, make sure its within allowed image width/height
        $imageConfiguration['height'] = min(max($imageConfiguration['height'], 1), $config['maxImageWidthHeight']);
    }


    if (!isset($imageConfiguration['collection'])){
        // if no collection is selected, revert to default
        $imageConfiguration['collection'] = $config['defaultCollection'];
    } else {
        // get all valid collections from input (comma separated)
        $imageConfiguration['collection'] = array_filter(explode(',', $imageConfiguration['collection']), function($collectionName){
            return $collectionName === 'random' || isset(collectionMap()[$collectionName]);
        });

        if ( count($imageConfiguration['collection']) === 0){
            // if no valid collections are left, revert to default
            $imageConfiguration['collection'] = $config['defaultCollection'];
        }
        elseif ( count($imageConfiguration['collection']) === 1 && $imageConfiguration['collection'][0] === 'random'){
            // if only option is random, set random as option
            $imageConfiguration['collection'] = 'random';
        }
        else {
            // random cannot be part of a collection set, remove as selectable option
            $imageConfiguration['collection'] = array_filter($imageConfiguration['collection'], function($collectionName){
                return $collectionName !== 'random';
            }); 

            // pick random collection from given input
            $imageConfiguration['collection'] = array_rand($imageConfiguration['collection']);
        }
    }

    // if selected option is random, pick random collection from available map options
    if ($imageConfiguration['collection'] === 'random'){
        $imageConfiguration['collection'] = array_rand(collectionMap());
    }


    // check if non random image exists in current imageMap, if not, fallback to random image
    if ($imageConfiguration['image'] !== 'random' && !array_key_exists($imageConfiguration['image'], imageMap($imageConfiguration['collection']))){
        $imageConfiguration['image'] = 'random';
    }

    // select random image if needed
    if ($imageConfiguration['image'] === 'random'){
        $imageConfiguration['image'] = array_rand(imageMap($imageConfiguration['collection']));
    }



    ///////////////////////////////////////////////////////////////////////////
    // 3. compile image from configuration
    ///////////////////////////////////////////////////////////////////////////

    // create cache key for given configuration
    $imageConfigurationCacheKey = flatten_cacheable_array($imageConfiguration);
    $imageCachedFilePath = $config['cachePath'] . $imageConfigurationCacheKey . '.jpg';


    // check if image for given cache key exists, if not, create image
    if (file_exists($imageCachedFilePath) === false){
        // autoload composer dependencies
        require __DIR__ . '/vendor/autoload.php';

        // create new image from given input image
        $image = new abeautifulsite\SimpleImage($config['imagePath'] . $imageConfiguration['collection'] . '/' . imageMap($imageConfiguration['collection'])[$imageConfiguration['image']]);

        // auto rotate according to exif data
        $image->auto_orient();

        // scale to height if width is auto and height is set explicitly
        if ( $imageConfiguration['width'] === 'auto' && $imageConfiguration['height'] !== 'auto' ){
            $image->fit_to_height($imageConfiguration['height']);
        }

        // scale to width if height is auto and width is set explicitly
        elseif( $imageConfiguration['width'] !== 'auto' && $imageConfiguration['height'] === 'auto' ){
            $image->fit_to_width($imageConfiguration['width']);
        }

        // create thumbnail where image fits best in given dimensions. Residue automatically cropped
        elseif( $imageConfiguration['width'] !== 'auto' && $imageConfiguration['height'] !== 'auto' ){
            $image->thumbnail($imageConfiguration['width'], $imageConfiguration['height']);
        }

        // save image on filesystem for caching
        $image->save($imageCachedFilePath);
    }
    


    ///////////////////////////////////////////////////////////////////////////
    // 4. serve image to user
    ///////////////////////////////////////////////////////////////////////////

    // mark image as cacheable
    header('Pragma: public');
    header('Cache-Control: max-age=86400');
    header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

    // send generated image mime type and size
    header('Content-Type: ' . mime_content_type($imageCachedFilePath));
    header('Content-Length: ' . filesize($imageCachedFilePath));

    // send image
    readfile($imageCachedFilePath);
