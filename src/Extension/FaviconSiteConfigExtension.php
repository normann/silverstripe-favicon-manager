<?php

namespace Normann\FaviconManager\Extension;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetContainer;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;
use ZipArchive;

/**
 * Populates favicon/manifest files on SiteConfig from an uploaded ZIP.
 */
class FaviconSiteConfigExtension extends DataExtension
{
    /**
     * @var string
     */
    private static $icons_folder_name = 'favicons';

    /**
     * @var string
     */
    private static $archives_folder_name = 'archives';

    /**
     * @var string[]
     */
    private static $has_one = [
        'FaviconArchiveZip'     => File::class,
        'Favicon'               => Image::class, // .ico image with size 48x48
        'FaviconSVG'            => File::class, // .svg file with name favicon.svg
        'Favicon96x96'          => Image::class, // .png image with size 96x96
        'WebAppManifest192x192' => Image::class, // .png image with size 192x192
        'WebAppManifest512x512' => Image::class, // .png image with size 512x512
        'AppleTouchIcon'        => Image::class, // .png image with size 180x180
        'Manifest'              => File::class,  // .json file
    ];

    /**
     * @var array
     */
    private static $file_to_handler_mapping = [
        'favicon.ico' => [
            'RelName' => 'Favicon',
            'Handler' => 'binary_copy',
            'Size'    => 'width="48" height="48"',
            'Group'   => 'core',
        ],
        'favicon-96x96.png' => [
            'RelName' => 'Favicon96x96',
            'Handler' => 'binary_copy',
            'Size'    => 'width="72" height="72"',
            'Group'   => 'core',
        ],
        'favicon.svg' => [
            'RelName' => 'FaviconSVG',
            'Handler' => 'binary_copy',
            'Size'    => 'width="48" height="48"', // matches favicon.ico — same functional role, not scaled down
            'Group'   => 'core',
        ],
        'apple-touch-icon.png' => [
            'RelName' => 'AppleTouchIcon',
            'Handler' => 'binary_copy',
            'Size'    => 'width="120" height="120"',
            'Group'   => 'core',
        ],
        'web-app-manifest-192x192.png' => [
            'RelName' => 'WebAppManifest192x192',
            'Handler' => 'binary_copy',
            'Size'    => 'width="168" height="168"',
            'Group'   => 'pwa',
        ],
        'web-app-manifest-512x512.png' => [
            'RelName' => 'WebAppManifest512x512',
            'Handler' => 'binary_copy',
            'Size'    => 'width="320" height="320"',
            'Group'   => 'pwa',
        ],
        'site.webmanifest' => [
            'RelName' => 'Manifest',
            'Handler' => 'manifestRewrite', // real method, called dynamically below
        ],
    ];

    /**
     * @throws ValidationException
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $faviconTab = Tab::create('Favicon');
        $fields->insertAfter('Access', $faviconTab);

        $fields->addFieldsToTab('Root.Favicon', $this->buildFaviconUploadFields());
    }

    public function onBeforeWrite(): void
    {
        // Only re-process when the ZIP itself changes
        if ($this->owner->isChanged('FaviconArchiveZipID')) {
            $this->clearAllExistingIconFiles();
            $this->rePopulatingAllIconFiles();
        }
    }

    /**
     * @throws ValidationException
     */
    private function buildFaviconUploadFields(): array
    {
        $fields = [];
        $this->owner->flushCache();
        $this->makeFilesystemReadyForProcessingFaviconPackage();

        $uploadFieldDescription = <<<'HTML'
<div class="message" style="border-left: 4px solid #05386b; padding: 10px 14px; font-size: 14px; line-height: 1.5;">
    <strong style="display:block; margin-bottom:4px;">How to generate your favicon files</strong>
    Upload the ZIP file from a favicon generator tool (we recommend
    <a href="https://realfavicongenerator.net/" target="_blank">realfavicongenerator.net</a>).
    If it asks where the files will be saved, leave that blank — it's handled automatically.
    <br><strong>Note:</strong> uploading a new ZIP replaces all current favicon files,
    so make sure it's the complete set first.
</div>
HTML;

        $fields[] = UploadField::create(
            'FaviconArchiveZip',
            'Favicon ZIP file'
        )
            ->setAttachEnabled(false)
            ->setDescription($uploadFieldDescription)
            ->setAllowedMaxFileNumber(1)
            ->setAllowedExtensions(['zip'])
            ->setFolderName($this->getPackageFolderPath());

        $fields = array_merge($fields, $this->buildIconFilesReadonly());

        return $fields;
    }

    private function buildIconFilesReadonly(): array
    {
        $fields = [];
        $groupedItems = [];
        $manifestField = null;

        foreach (self::$file_to_handler_mapping as $fileName => $handleConfig) {
            $relation = $handleConfig['RelName'];
            $fileID   = $this->owner->{$relation . 'ID'};
            $file     = $fileID ? File::get_by_id($fileID) : null;

            if (!$file || !$file->exists()) {
                continue;
            }

            if ($handleConfig['Handler'] === 'manifestRewrite') {
                $content = @$file->getString();
                $escaped = htmlspecialchars($content ?: '', ENT_QUOTES, 'UTF-8');
                $html = <<<HTML
<div class="form-group field readonly">
    <label class="form__field-label">{$fileName}</label>
    <div class="form__field-holder">
        <details>
            <summary style="cursor:pointer;">View generated manifest.json</summary>
            <pre style="max-height:300px; overflow:auto; background:#f6f7f8; padding:8px;
                margin-top:6px; border-radius:4px; font-size:12px;">{$escaped}</pre>
        </details>
    </div>
</div>
HTML;
                // Held back so it always renders last, regardless of array position.
                $manifestField = LiteralField::create($relation . '_readonly', $html)->setTitle($fileName);
                continue;
            }

            if (is_a($file, Image::class) || mb_strtolower($file->getExtension()) === 'svg') {
                $imgSrc   = $file->Link() . '?v=' . $file->ID . '_' . $file->Version;
                $imgAlt   = $file->Name;
                $sizeAttr = $handleConfig['Size'] ?? '';
                $group    = $handleConfig['Group'] ?? 'ungrouped';

                $itemHtml = <<<HTML
<div class="favicon-preview-item" style="flex:0 0 auto; min-width:120px; text-align:center;">
    <div style="font-size:13px; font-weight:600; color:#1a1a1a; white-space:nowrap; overflow:hidden;
        text-overflow:ellipsis; max-width:100%; margin-bottom:8px;" title="{$fileName}">
        {$fileName}
    </div>
    <div>
        <img src="{$imgSrc}" alt="{$imgAlt}" {$sizeAttr}>
    </div>
</div>
HTML;

                $groupedItems[$group][] = $itemHtml;
            }
        }

        foreach ($groupedItems as $group => $items) {
            $itemsHtml = implode("\n", $items);
            $rowHtml = <<<HTML
<div class="form-group field readonly favicon-preview-row">
    <label class="form__field-label"></label>
    <div class="form__field-holder" style="display:flex; flex-wrap:wrap; gap:16px;">
        {$itemsHtml}
    </div>
</div>
HTML;
            $fields[] = LiteralField::create('FaviconGroup_' . $group, $rowHtml);
        }

        if ($manifestField) {
            $fields[] = $manifestField;
        }

        return $fields;
    }

    /**
     * Wipes previously generated icon files before re-populating from the new ZIP.
     */
    private function clearAllExistingIconFiles(): void
    {
        foreach (array_keys(self::$has_one) as $fileRelationName) {
            if ($fileRelationName === 'FaviconArchiveZip') {
                $changes = $this->owner->getChangedFields(true);

                if (isset($changes['FaviconArchiveZipID'], $changes['FaviconArchiveZipID']['before'])) {
                    $originalFileID = $changes['FaviconArchiveZipID']['before'];

                    if ($originalFileID) {
                        $file = File::get_by_id($originalFileID);

                        if ($file && $file->exists()) {
                            $file->delete();
                        }
                    }
                }

                continue;
            }

            $file = $this->owner->{$fileRelationName}();

            if ($file && $file->exists()) {
                $file->doArchive();
            }

            $this->owner->{$fileRelationName . 'ID'} = 0;
        }
    }

    /**
     * Extracts the ZIP and creates/updates each mapped favicon file.
     *
     * @throws NotFoundExceptionInterface
     */
    private function rePopulatingAllIconFiles(): void
    {
        $package = File::get_by_id($this->owner->FaviconArchiveZipID);

        if ($package && $package->exists()) {
            $tmpZip = tempnam(sys_get_temp_dir(), 'favicon-archive-');

            $source = $package->getStream();
            if (!$source) {
                Injector::inst()->get(LoggerInterface::class)->warning(sprintf(
                    'FaviconSiteConfigExtension: could not read uploaded ZIP stream for SiteConfig #%d',
                    $this->owner->ID
                ));

                unlink($tmpZip);

                return;
            }

            $dest = fopen($tmpZip, 'wb');
            stream_copy_to_stream($source, $dest);
            fclose($dest);
            fclose($source);

            $zipHandler = new ZipArchive();

            if ($zipHandler->open($tmpZip) === true) {
                for ($i = 0; $i < $zipHandler->numFiles; ++$i) {
                    $filename = $zipHandler->getNameIndex($i);
                    $fileinfo = pathinfo($filename);

                    if (array_key_exists($fileinfo['basename'], self::$file_to_handler_mapping)) {
                        if (self::$file_to_handler_mapping[$fileinfo['basename']]['Handler'] === 'binary_copy') {
                            $target = $this->getTempFilePath($fileinfo['basename']);

                            $copied = copy('zip://' . $tmpZip . '#' . $filename, $target);

                            if (!$copied) {
                                Injector::inst()->get(LoggerInterface::class)->warning(sprintf(
                                    'FaviconSiteConfigExtension: failed to extract "%s" from ZIP for SiteConfig #%d',
                                    $filename,
                                    $this->owner->ID
                                ));

                                continue;
                            }

                            $convertedFileName = $fileinfo['basename'];
                        } else {
                            $handleFunc = self::$file_to_handler_mapping[$fileinfo['basename']]['Handler'];
                            $convertedFileName = $this->{$handleFunc}(
                                $zipHandler,
                                $filename,
                                $this->getIconsFolderPath()
                            );
                        }

                        $file = $this->syncTmpFileIntoFile($convertedFileName);

                        if ($file instanceof File) {
                            $file->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

                            $fileNameID = self::$file_to_handler_mapping[$fileinfo['basename']]['RelName'] . 'ID';
                            $this->owner->{$fileNameID} = $file->ID;
                        }
                    }
                }

                $zipHandler->close();
            } else {
                Injector::inst()->get(LoggerInterface::class)->warning(sprintf(
                    'FaviconSiteConfigExtension: could not open uploaded ZIP for SiteConfig #%d',
                    $this->owner->ID
                ));
            }

            unlink($tmpZip);
        }
    }

    /**
     * Mimics a real upload so the resulting File record gets proper hashing, versioning and permissions.
     *
     * @see FileField::saveInto()
     * @see Upload::loadIntoFile()
     *
     * @throws Exception
     */
    private function syncTmpFileIntoFile(string $fileName): AssetContainer
    {
        $fileClass = File::get_class_for_file_extension(
            File::get_file_extension($fileName)
        );

        $file    = Injector::inst()->create($fileClass);
        $tmpPath = $this->getTempFilePath($fileName);

        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(['ico', 'png', 'svg', 'json', 'xml']);

        Upload_Validator::config()->set('use_is_uploaded_file', false);

        $tmpFile = [
            'name'     => $fileName,
            'type'     => @mime_content_type($tmpPath) ?: 'application/octet-stream',
            'error'    => UPLOAD_ERR_OK,
            'size'     => file_exists($tmpPath) ? filesize($tmpPath) : 0,
            'tmp_name' => $tmpPath,
        ];

        $upload->loadIntoFile($tmpFile, $file, $this->getIconsFolderPath());

        if ($upload->isError()) {
            throw new Exception(sprintf(
                'FaviconSiteConfigExtension: failed to save "%s" as a File record: %s',
                $fileName,
                implode('; ', $upload->getErrors())
            ));
        }

        return $upload->getFile();
    }

    private function manifestRewrite(ZipArchive $zipHandler, string $zipPath, string $destPath): string
    {
        $fp = $zipHandler->getStream($zipPath);
        $contents = '';
        if ($fp) {
            $contents = stream_get_contents($fp);
            fclose($fp);
        }

        if ($contents) {
            // Rewrite each icon's src to the real asset path, e.g. /assets/favicons/site1/web-app-manifest-192x192.png
            // (or /assets/{subsite-base-folder}/favicons/site2/... for a subsite)
            $altered = $contents = json_decode($contents, true);

            if (isset($contents['icons']) && is_array($contents['icons'])) {
                foreach ($contents['icons'] as $index => $icon) {
                    if (isset($icon['src'])) {
                        $segments                        = explode('/', $icon['src']);
                        $altered['icons'][$index]['src'] = '/' . ASSETS_DIR . '/' . $destPath . '/' . end($segments);
                    }
                }
            }

            $tmpTarget = $this->getTempFilePath('manifest.json');
            $manifest  = fopen($tmpTarget, 'w');

            if ($manifest) {
                fwrite($manifest, json_encode($altered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fclose($manifest);
            }
        }

        return 'manifest.json';
    }

    /**
     * @throws ValidationException
     */
    private function makeFilesystemReadyForProcessingFaviconPackage(): void
    {
        Folder::find_or_make($this->getIconsFolderPath());

        // make sure the physical folder is created
        if (!file_exists(ASSETS_PATH . DIRECTORY_SEPARATOR . $this->getIconsFolderPath())) {
            Filesystem::makeFolder(ASSETS_PATH . DIRECTORY_SEPARATOR . $this->getIconsFolderPath());
        }

        $archivesFolder = Folder::find_or_make($this->getPackageFolderPath());

        // Lock down the archive folder — the ZIP is a source file only, never served directly
        if ($archivesFolder->CanViewType !== 'OnlyTheseUsers') {
            $archivesFolder->CanViewType = 'OnlyTheseUsers';
            $archivesFolder->write();
            $archivesFolder->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        }
    }

    private function getIconsFolderPath(): string
    {
        $folderPath = $this->owner->config()->get('icons_folder_name')
            . DIRECTORY_SEPARATOR . 'site' . (string) $this->owner->ID;

        if (class_exists(Subsite::class) && singleton(Subsite::class)->hasField('BaseFolder')) {
            $subsite = Subsite::currentSubsite();

            if ($subsite && $subsite->exists()) {
                return $subsite->prefixFolderWithSubsiteBaseFolder($folderPath);
            }
        }

        return $folderPath;
    }

    private function getPackageFolderPath(): string
    {
        $iconsFolderPath = $this->getIconsFolderPath();

        return $iconsFolderPath . DIRECTORY_SEPARATOR . $this->owner->config()->get('archives_folder_name');
    }

    private function getTempFilePath(string $fileName): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'favicon-' . $this->owner->ID . '-' . $fileName;
    }

    /**
     * Cache key for the Favicons template partial — changes on any SiteConfig save
     * or when a mapped favicon file changes.
     */
    public function getFaviconsCacheKey(): string
    {
        $parts = [
            'Favicons',
            $this->owner->ID,
            $this->owner->LastEdited,
        ];

        foreach (self::$file_to_handler_mapping as $handleConfig) {
            $file = $this->owner->{$handleConfig['RelName']};
            $parts[] = $file && $file->exists() ? $file->ID . '_' . $file->Version : '0';
        }

        return implode('-', $parts);
    }
}
