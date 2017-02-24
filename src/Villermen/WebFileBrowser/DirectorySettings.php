<?php

namespace Villermen\WebFileBrowser;


class DirectorySettings
{
    /**
     * Contains all parsed configuration directories in order.
     * For debugging purposes.
     *
     * @var string[]
     */
    protected $parsedConfigurations = [];

    /**
     * @var bool
     */
    protected $displayWebpages = false;

    /**
     * @var bool
     */
    protected $displayDirectories = false;

    /**
     * @var bool
     */
    protected $displayFiles = false;

    /**
     * @var string[]
     */
    protected $webpageBlacklist = [];

    /**
     * @var string[]
     */
    protected $directoryBlacklist = [];

    /**
     * @var string[]
     */
    protected $fileBlacklist = [];

    /**
     * @var string[]
     */
    protected $webpageWhitelist = [];

    /**
     * @var string[]
     */
    protected $directoryWhitelist = [];

    /**
     * @var string[]
     */
    protected $fileWhitelist = [];

    /**
     * @var string
     */
    protected $description = false;

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string[]
     */
    public function getFileWhitelist(): array
    {
        return $this->fileWhitelist;
    }

    /**
     * @param string[] $fileWhitelist
     */
    public function setFileWhitelist(array $fileWhitelist)
    {
        $this->fileWhitelist = $fileWhitelist;
    }

    /**
     * @param string[] $fileWhitelist
     */
    public function addFileWhitelist(array $fileWhitelist)
    {
        $this->fileWhitelist = array_merge($this->fileWhitelist, $fileWhitelist);
    }

    /**
     * @return string[]
     */
    public function getDirectoryWhitelist(): array
    {
        return $this->directoryWhitelist;
    }

    /**
     * @param string[] $directoryWhitelist
     */
    public function setDirectoryWhitelist(array $directoryWhitelist)
    {
        $this->directoryWhitelist = $directoryWhitelist;
    }

    /**
     * @param string[] $directoryWhitelist
     */
    public function addDirectoryWhitelist(array $directoryWhitelist)
    {
        $this->directoryWhitelist = array_merge($this->directoryWhitelist, $directoryWhitelist);
    }

    /**
     * @return bool
     */
    public function isDisplayWebpages(): bool
    {
        return $this->displayWebpages;
    }

    /**
     * @param bool $displayWebpages
     */
    public function setDisplayWebpages(bool $displayWebpages)
    {
        $this->displayWebpages = $displayWebpages;
    }

    /**
     * @return bool
     */
    public function isDisplayDirectories(): bool
    {
        return $this->displayDirectories;
    }

    /**
     * @param bool $displayDirectories
     */
    public function setDisplayDirectories(bool $displayDirectories)
    {
        $this->displayDirectories = $displayDirectories;
    }

    /**
     * @return bool
     */
    public function isDisplayFiles(): bool
    {
        return $this->displayFiles;
    }

    /**
     * @param bool $displayFiles
     */
    public function setDisplayFiles(bool $displayFiles)
    {
        $this->displayFiles = $displayFiles;
    }

    /**
     * @return string[]
     */
    public function getWebpageBlacklist(): array
    {
        return $this->webpageBlacklist;
    }

    /**
     * @param string[] $webpageBlacklist
     */
    public function setWebpageBlacklist(array $webpageBlacklist)
    {
        $this->webpageBlacklist = $webpageBlacklist;
    }

    /**
     * @param string[] $webpageBlacklist
     */
    public function addWebpageBlacklist(array $webpageBlacklist)
    {
        $this->webpageBlacklist = array_merge($this->webpageBlacklist, $webpageBlacklist);
    }

    /**
     * @return \string[]
     */
    public function getWebpageWhitelist(): array
    {
        return $this->webpageWhitelist;
    }

    /**
     * @param string[] $webpageWhitelist
     */
    public function setWebpageWhitelist(array $webpageWhitelist)
    {
        $this->webpageWhitelist = $webpageWhitelist;
    }

    /**
     * @param string[] $webpageWhitelist
     */
    public function addWebpageWhitelist(array $webpageWhitelist)
    {
        $this->webpageWhitelist = array_merge($this->webpageWhitelist, $webpageWhitelist);
    }

    /**
     * @return \string[]
     */
    public function getDirectoryBlacklist(): array
    {
        return $this->directoryBlacklist;
    }

    /**
     * @param \string[] $directoryBlacklist
     */
    public function setDirectoryBlacklist(array $directoryBlacklist)
    {
        $this->directoryBlacklist = $directoryBlacklist;
    }

    /**
     * @param \string[] $directoryBlacklist
     */
    public function addDirectoryBlacklist(array $directoryBlacklist)
    {
        $this->directoryBlacklist = array_merge($this->directoryBlacklist, $directoryBlacklist);
    }

    /**
     * @return \string[]
     */
    public function getFileBlacklist(): array
    {
        return $this->fileBlacklist;
    }

    /**
     * @param \string[] $fileBlacklist
     */
    public function setFileBlacklist(array $fileBlacklist)
    {
        $this->fileBlacklist = $fileBlacklist;
    }

    /**
     * @param \string[] $fileBlacklist
     */
    public function addFileBlacklist(array $fileBlacklist)
    {
        $this->fileBlacklist = array_merge($this->fileBlacklist, $fileBlacklist);
    }

    /**
     * @return \string[]
     */
    public function getParsedConfigurations(): array
    {
        return $this->parsedConfigurations;
    }

    /**
     * @param string $parsedConfiguration
     */
    public function addParsedConfiguration(string $parsedConfiguration)
    {
        $this->parsedConfigurations[] = $parsedConfiguration;
    }
}