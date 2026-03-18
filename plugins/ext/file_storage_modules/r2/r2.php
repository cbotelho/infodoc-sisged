<?php

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class r2
{
    public $title;
    public $site;
    public $api;
    public $version;

    function __construct()
    {
        $this->title = TEXT_MODULE_R2_TITLE;
        $this->site = 'https://developers.cloudflare.com/r2/';
        $this->api = 'https://docs.aws.amazon.com/aws-sdk-php/';
        $this->version = '1.0';
    }

    public function configuration()
    {
        $cfg = array();

        $cfg[] = array(
            'key' => 'endpoint',
            'type' => 'input',
            'default' => '',
            'title' => 'S3 endpoint',
            'description' => TEXT_MODULE_R2_ACCESS_INFO,
            'params' => array('class' => 'form-control input-xlarge'),
        );

        $cfg[] = array(
            'key' => 'region',
            'type' => 'input',
            'default' => 'auto',
            'title' => 'Region',
            'params' => array('class' => 'form-control input-medium'),
        );

        $cfg[] = array(
            'key' => 'bucket',
            'type' => 'input',
            'default' => '',
            'title' => 'Bucket',
            'params' => array('class' => 'form-control input-large'),
        );

        $cfg[] = array(
            'key' => 'access_key_id',
            'type' => 'input',
            'default' => '',
            'title' => 'Access key ID',
            'params' => array('class' => 'form-control input-xlarge'),
        );

        $cfg[] = array(
            'key' => 'secret_access_key',
            'type' => 'input',
            'default' => '',
            'title' => 'Secret access key',
            'params' => array('class' => 'form-control input-xlarge'),
        );

        $cfg[] = array(
            'key' => 'object_prefix',
            'type' => 'input',
            'default' => 'ged',
            'title' => 'Object prefix',
            'description' => 'Opcional. Exemplo: ged, producao/gea ou documentos.',
            'params' => array('class' => 'form-control input-large'),
        );

        return $cfg;
    }

    protected function require_sdk()
    {
        static $loaded = false;

        if($loaded)
        {
            return;
        }

        $autoload = __DIR__ . '/vendor/autoload.php';

        if(!is_file($autoload))
        {
            throw new RuntimeException('AWS SDK nao encontrada. Execute composer install em plugins/ext/file_storage_modules/r2 ou gere a imagem Docker atualizada.');
        }

        require_once $autoload;
        $loaded = true;
    }

    protected function get_cfg_value($cfg, $key, $default = '')
    {
        $env_map = array(
            'endpoint' => 'FILE_STORAGE_R2_ENDPOINT',
            'region' => 'FILE_STORAGE_R2_REGION',
            'bucket' => 'FILE_STORAGE_R2_BUCKET',
            'access_key_id' => 'FILE_STORAGE_R2_ACCESS_KEY_ID',
            'secret_access_key' => 'FILE_STORAGE_R2_SECRET_ACCESS_KEY',
            'object_prefix' => 'FILE_STORAGE_R2_OBJECT_PREFIX',
        );

        if(isset($env_map[$key]))
        {
            $env_value = getenv($env_map[$key]);

            if($env_value !== false && strlen(trim($env_value)))
            {
                return trim($env_value);
            }
        }

        if(isset($cfg[$key]) && strlen(trim($cfg[$key])))
        {
            return trim($cfg[$key]);
        }

        return $default;
    }

    protected function get_client($cfg)
    {
        $this->require_sdk();

        $endpoint = $this->get_cfg_value($cfg, 'endpoint');
        $region = $this->get_cfg_value($cfg, 'region', 'auto');
        $access_key_id = $this->get_cfg_value($cfg, 'access_key_id');
        $secret_access_key = $this->get_cfg_value($cfg, 'secret_access_key');
        $bucket = $this->get_cfg_value($cfg, 'bucket');

        if(!strlen($endpoint) || !strlen($access_key_id) || !strlen($secret_access_key) || !strlen($bucket))
        {
            throw new RuntimeException($this->title . ': configuracao incompleta. Informe endpoint, bucket, access key ID e secret access key.');
        }

        return new S3Client(array(
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'credentials' => array(
                'key' => $access_key_id,
                'secret' => $secret_access_key,
            ),
            'signature_version' => 'v4',
        ));
    }

    protected function get_bucket($cfg)
    {
        return $this->get_cfg_value($cfg, 'bucket');
    }

    protected function build_object_key($cfg, $file)
    {
        $parts = array();
        $prefix = trim($this->get_cfg_value($cfg, 'object_prefix', ''), '/');

        if(strlen($prefix))
        {
            $parts[] = $prefix;
        }

        $parts[] = 'attachments';
        $parts[] = trim($file['folder'], '/');
        $parts[] = $file['file'];

        return implode('/', array_filter($parts, 'strlen'));
    }

    protected function prepare_file($filename)
    {
        $file = attachments::parse_filename($filename);

        if(!isset($file['file_path']) || !is_file($file['file_path']))
        {
            throw new RuntimeException('Arquivo local nao encontrado para sincronizacao: ' . $filename);
        }

        return $file;
    }

    function upload($module_id, $queue_info)
    {
        $cfg = modules::get_configuration($this->configuration(), $module_id);

        try
        {
            $file = $this->prepare_file($queue_info['filename']);
            $client = $this->get_client($cfg);

            $client->putObject(array(
                'Bucket' => $this->get_bucket($cfg),
                'Key' => $this->build_object_key($cfg, $file),
                'SourceFile' => $file['file_path'],
                'ContentType' => (strlen($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream'),
            ));

            unlink($file['file_path']);
            file_storage::remove_from_queue($queue_info['id']);
        }
        catch(Exception $e)
        {
            file_storage::remove_from_queue($queue_info['id']);

            if(isset($file) && is_array($file))
            {
                modules::log_file_storage($this->title . ': ' . $e->getMessage(), $file);
            }

            die($this->title . ': ' . $e->getMessage());
        }
    }

    function download($module_id, $filename)
    {
        $cfg = modules::get_configuration($this->configuration(), $module_id);
        $file = attachments::parse_filename($filename);

        try
        {
            $client = $this->get_client($cfg);
            $tmp_path = DIR_FS_TMP . $file['file'];

            $client->getObject(array(
                'Bucket' => $this->get_bucket($cfg),
                'Key' => $this->build_object_key($cfg, $file),
                'SaveAs' => $tmp_path,
            ));

            if(is_file($tmp_path))
            {
                file_storage::download_file_content($file['name'], $tmp_path);
            }
        }
        catch(Exception $e)
        {
            modules::log_file_storage($this->title . ': ' . $e->getMessage(), $file);
        }
    }

    function download_files($module_id, $files)
    {
        $cfg = modules::get_configuration($this->configuration(), $module_id);

        try
        {
            $client = $this->get_client($cfg);

            foreach(explode(',', $files) as $filename)
            {
                $file = attachments::parse_filename($filename);

                $client->getObject(array(
                    'Bucket' => $this->get_bucket($cfg),
                    'Key' => $this->build_object_key($cfg, $file),
                    'SaveAs' => DIR_FS_TMP . $file['file'],
                ));
            }
        }
        catch(Exception $e)
        {
            modules::log_file_storage($this->title . ': ' . $e->getMessage(), $file);
            die($this->title . ': ' . $e->getMessage());
        }

        $zip = new ZipArchive();
        $zip_filename = 'attachments-' . time() . '.zip';
        $zip_filepath = DIR_FS_TMP . $zip_filename;
        $zip->open($zip_filepath, ZipArchive::CREATE);

        foreach(explode(',', $files) as $filename)
        {
            $file = attachments::parse_filename($filename);
            $zip->addFile(DIR_FS_TMP . $file['file'], $file['name']);
        }

        $zip->close();

        if(!is_file($zip_filepath))
        {
            exit('Error: cannot create zip archive in ' . $zip_filepath);
        }

        foreach(explode(',', $files) as $filename)
        {
            $file = attachments::parse_filename($filename);
            @unlink(DIR_FS_TMP . $file['file']);
        }

        file_storage::download_file_content($zip_filename, $zip_filepath);
        exit();
    }

    function delete($module_id, $files = array())
    {
        $cfg = modules::get_configuration($this->configuration(), $module_id);

        try
        {
            $client = $this->get_client($cfg);

            foreach($files as $filename)
            {
                $file = attachments::parse_filename($filename);

                try
                {
                    $client->deleteObject(array(
                        'Bucket' => $this->get_bucket($cfg),
                        'Key' => $this->build_object_key($cfg, $file),
                    ));
                }
                catch(AwsException $e)
                {
                    modules::log_file_storage($this->title . ': ' . $e->getMessage(), $file);
                }
            }
        }
        catch(Exception $e)
        {
        }
    }
}