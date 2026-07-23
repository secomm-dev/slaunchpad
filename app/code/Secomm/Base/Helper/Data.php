<?php
namespace Secomm\Base\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManage;

    /**
     * @var DirectoryList
     */
    protected $_dir;

    /** @var \Magento\Framework\Filesystem\Io\File  */
    protected $ioFile;

    protected $dateTime;

    /** @var ResourceConnection  */
    protected $resourceConnection;

    /** @var \Magento\Framework\DB\Adapter\AdapterInterface  */
    protected $connection;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManage,
        DirectoryList $dir,
        \Magento\Framework\Filesystem\Io\File $ioFile,
        DateTime $dateTime,
        ResourceConnection $resourceConnection
    ) {
        $this->_storeManage = $storeManage;
        $this->_dir = $dir;
        $this->ioFile = $ioFile;
        $this->dateTime = $dateTime;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        parent::__construct($context);
    }

    /**
     * Create folder and permissions
     * @param string $path
     * @param int $mod should be 0777
     * @param string $delimiter
     */
    public function mkdirChmod($path, $mod, $delimiter = '/var/')
    {
        $baseDir = BP . "/" . trim($delimiter, '/') . "/";
        $arrPath = explode($delimiter, $path);
        $arrPath = $arrPath[count($arrPath) - 1];
        $arrPath = explode("/", $arrPath);
        for ($i = 0; $i < count($arrPath) - 1; $i++) {
            $baseDir .= $arrPath[$i] . "/";
            if (!file_exists($baseDir)) {
                @mkdir($baseDir, $mod);
                @chmod($baseDir, $mod);
            }
        }
    }

    public function getLogger()
    {
        return $this->_logger;
    }

    public function setLogger(\Zend\Log\Logger $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * Create new logger
     * @param $logPath
     * @param $logFileName
     * @param bool $inShell
     * @return \Secomm\Base\Model\Logger
     * @throws \Exception
     */
    public function createLogger($logPath, $logFileName, $inShell = false)
    {
        $this->ioFile->setAllowCreateFolders(true);
        $this->ioFile->checkAndCreateFolder($logPath);
        $logFilePath = rtrim($logPath, '/') . '/' . $logFileName;
        $writer = new \Zend\Log\Writer\Stream($logFilePath);
        $logger = new \Secomm\Base\Model\Logger();
        $logger->addWriter($writer);
        $logger->setInShell($inShell);
        return $logger;
    }

    /**
     * @param $fileName
     * @return bool
     */
    public function createFlagFile($fileName)
    {
        $path = BP . "/" . 'var' . "/" . 'log' . "/" . $fileName;
        $handle = @fopen($path, "w");
        @fclose($handle);

        if (file_exists($path)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return mixed
     */
    public function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {   //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {   //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function getCurrentMonth()
    {
        return $this->dateTime->date('m');
    }

    public function getCurrentYear()
    {
        return $this->dateTime->date('Y');
    }

    /**
     * Return Customer group id by customer group code/name
     * @param $name
     * @return string|null
     */
    public function getCustomerGroupIdByName($name)
    {
        $tableName = $this->connection->getTableName('customer_group');
        $groupId = $this->connection->fetchOne(
            $this->connection->select()
                ->from($tableName, ['customer_group_id'])
                ->where('customer_group_code LIKE ?', $name)
        );
        return $groupId ?? null;
    }
}
