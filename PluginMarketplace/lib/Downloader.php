<?php 
/**
 * Piwik - Open source web analytics
 *
 * @link http://plugin.suenkel.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @author Christian Suenkel <christian@suenkel.de>
 *
 * @category Piwik_Plugins
 * @package  Piwik_PluginMarketplace
 */

/**
 * Library: Downloader
 *
 * this class handles
 * - donwloading zip files via http-connection
 * - extracting the zip-files
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Downloader
{

    /**
     * default relative path (below PIWIK_USER_PATH) to the download cache
     * @var string
     */
    const PATH_TO_DOWNLOAD = '/tmp/download/';

    /**
     * Relative path (below PIWIK_USER_PATH), where the downloadfile should be stored
     * @var string
     */
    protected $workspace = null;

    /**
     * Absoulte path, where the zip-file resides
     * @var string
     */
    protected $filename = null;

    /**
     * Absoulte path, where the extracted files could be found
     * @var string
     */
    protected $extracted = null;


    /**
     * Classconstructor
     * optional with a relative path to be set as bas
     * @param string|null $downloadRelativePath - path below PIWIK_USER_PATH
     */
    public function __construct($downloadRelativePath = null)
    {
        $this->setWorkspace($downloadRelativePath);
    }


    /**
     * prepare the environment for a new download
     * - unlink old downloads
     * - check if writable
     *
     * @param string $filename - relative path within PIWIK_USER_PATH/$workspace
     * @return string - realpath of the file
     */
    protected function prepareNewTargetFilename($filename = null)
    {
        if($this->filename !== NULL) {
            $this->unlink();
        }
        if($filename === null) {
            $filename="autodownload";
        }
        Piwik::checkDirectoriesWritableOrDie( array($this->workspace) );
        $realpath= PIWIK_USER_PATH . $this->workspace;
        $filename = realpath( $realpath ) . DIRECTORY_SEPARATOR .basename($filename);
        return $filename;
    }


    /**
     * Download a File (url) via http and store it to the filename
     * @param string $url
     * @param string $filename - filename -relative to PIWIK_USER_PATH/$workspace/
     * @return string
     */
    public function download($url, $filename = null)
    {
        $filename=$this->prepareNewTargetFilename($filename);
        // defined('IS_PHPUNIT') && printf ("%s: \nURL: %s \nFilename:%s\n", __METHOD__, $url, $filename);
        if(Piwik_Http::fetchRemoteFile($url,$filename)){
            $this->setFilename($filename);
        }
        $this->checkFile();
        return $this->filename;
    }

    /**
     * side-entry for uploaded Zip-files
     * @param string $tmpFilename - tmpfile (aka, move_upload)
     * @param string $filename - target filename within PIWIK_USER_PATH/$workspace
     * @throws RuntimeException - if no copy to the workspace is posible
     * @return string
     */

    public function upload($tmpFilename, $filename = null )
    {
        $filename=$this->prepareNewTargetFilename($filename);
        if( !copy($tmpFilename, $filename)) {
            // !move_uploaded_file($tmpFilename, $filename) ){
            throw new RuntimeException($tmpFilename. Piwik_TranslateException('APUA_Exception_Downlaod_nouploadmove'));
        }
        $this->setFilename($filename)->checkFile();
        return $this->filename;
    }


    /**
     * check the file integrity
     * - size, existance, readable...
     * @throws RuntimeException - if the file is not usable
     * @return PluginMarketplace_Downloader
     */
    protected function checkFile()
    {
        if($this->filename === null
                ||  !file_exists($this->filename)
                || !is_readable($this->filename)){
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Downlaod_notexist', $this->filename));
        }
        if( filesize ($this->filename) < 200 ){
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Download_toosmall', $this->filename));
        }
        //TODO: check signature, if available
        return $this;
    }


    /**
     * extract/unpack the processed downloadfiel to the
     * relative path within PIWIK_USER_PATH/$basdir
     * @param string $relativePath
     * @throws RuntimeException
     * @throws Exception
     * @return string - realpath of extracted root-dir
     */
    public function extract($relativePath = null)
    {
        if( $relativePath == null ){
            $relativePath = 'extracted';
        }
        // @SMELL: check path ".." ?
        $zipfilename = $this->getFilename();

        // calculate path to extract
        $realpath = $this->workspace . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR;
        Piwik::checkDirectoriesWritableOrDie( array($realpath) );
        $realpath = realpath(PIWIK_USER_PATH . $realpath);
        if(file_exists($realpath)) {
            Piwik::unlinkRecursive($realpath, true);
        }

        /*
         * Unzip the file
        */
        $archive = Piwik_Unzip::factory('PclZip', $this->getFilename());

        if ( 0 == ($archive_files = $archive->extract($realpath) ) ){
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Download_ArchiveIncompatible', $archive->errorInfo()));
        }

        if ( 0 == count($archive_files) ){
            throw new Exception(Piwik_TranslateException('APUA_Exception_Download_ArchiveEmpty'));
        }

        foreach($archive_files as $archive_file) {
            if($archive_file['status'] !== 'ok'){
                throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Downlaod_ArchiveIncompatible'));
            }
        }
        $this->extracted = $realpath;
        return $this->extracted;
    }

    

    /**
     * unlink all temporary files (donwload and extract dir)
     * @throws RuntimeException - if not possible
     * @return boolean
     */
    public function unlink()
    {
        // unlink downloadfile
        if($this->filename != null
                && file_exists($this->filename)
                && !unlink($this->filename))
        {
            throw new RuntimeException(Piwik_Translate('APUA_Exception_Download_unlink', $this->filename));
        }
        $this->filename = null;

        // unlink unpack
        if($this->extracted == null){
            return true;
        }

        if(is_dir($this->extracted)){
            Piwik::unlinkRecursive($this->extracted, true);
        }
        if(file_exists($this->extracted)){
            throw new RuntimeException(Piwik_Translate('APUA_Exception_Download_unlinkdir', $this->extracted));
        }
        $this->extracted = null;
        return true;
    }


    /*
     * getter and setter
    */
    /**
     * get the realpath of the processed file
     * @throws RuntimeException - if the file is not available
     * @return string
     */
    public function getFilename()
    {
        $this->checkFile();
        return $this->filename;
    }


    /**
     * set the realpath of the filename to be processed
     * @param string $filename
     * @return PluginMarketplace_Downloader
     */
    public function setFilename($absfilename)
    {
        $this->filename = $absfilename;
        return $this;
    }


    /**
     * get the path to the extracted Content
     * @throws RuntimeException - if not available
     * @return string
     */
    public function getextractedPath()
    {
        if( $this->extracted == null ){
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Download_noextract'));
        }
        if( !is_dir($this->extracted)){
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Downlaod_noextractdir', $this->extracted));
        }
        return $this->extracted;
    }


    /**
     * set the absoltute path, where
     * @param string  $absolutePath
     * @return PluginMarketplace_Downloader
     */
    public function setextractedPath($absolutePath)
    {
        // SMELL: dangourous
        $this->extracted = $absolutePath;
        return $this;
    }


    
    /**
     * set the downloadbasdir within PIWIK_USER_PATH
     * @param string $downloadRelativePath
     * @return PluginMarketplace_Downloader
     */
    public function setWorkspace($downloadRelativePath = null){

        // @SMELL: check Dir against ".." etc
        if($downloadRelativePath === null) {
            $downloadRelativePath=self::PATH_TO_DOWNLOAD;
        }
        if(substr($downloadRelativePath,0,1) !== DIRECTORY_SEPARATOR) {
            $downloadRelativePath = DIRECTORY_SEPARATOR . $downloadRelativePath;
        }
        $this->workspace = $downloadRelativePath;
        return $this;
    }
}