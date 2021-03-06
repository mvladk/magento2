<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Tools\Di\App\Task\Operation;

use Magento\Tools\Di\App\Task\OperationInterface;
use Magento\Framework\App;
use Magento\Tools\Di\Compiler\Config;
use Magento\Tools\Di\Definition\Collection as DefinitionsCollection;

class Area implements OperationInterface
{
    /**
     * @var App\AreaList
     */
    private $areaList;

    /**
     * @var \Magento\Tools\Di\Code\Reader\Decorator\Area
     */
    private $areaInstancesNamesList;

    /**
     * @var Config\Reader
     */
    private $configReader;

    /**
     * @var Config\WriterInterface
     */
    private $configWriter;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var \Magento\Tools\Di\Compiler\Config\ModificationChain
     */
    private $modificationChain;

    /**
     * @param App\AreaList $areaList
     * @param \Magento\Tools\Di\Code\Reader\Decorator\Area $areaInstancesNamesList
     * @param Config\Reader $configReader
     * @param Config\WriterInterface $configWriter
     * @param \Magento\Tools\Di\Compiler\Config\ModificationChain $modificationChain
     * @param array $data
     */
    public function __construct(
        App\AreaList $areaList,
        \Magento\Tools\Di\Code\Reader\Decorator\Area $areaInstancesNamesList,
        Config\Reader $configReader,
        Config\WriterInterface $configWriter,
        Config\ModificationChain $modificationChain,
        $data = []
    ) {
        $this->areaList = $areaList;
        $this->areaInstancesNamesList = $areaInstancesNamesList;
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->data = $data;
        $this->modificationChain = $modificationChain;
    }

    /**
     * {@inheritdoc}
     */
    public function doOperation()
    {
        if (empty($this->data)) {
            return;
        }

        $definitionsCollection = new DefinitionsCollection();
        foreach ($this->data as $path) {
            $definitionsCollection->addCollection($this->getDefinitionsCollection($path));
        }

        $areaCodes = array_merge([App\Area::AREA_GLOBAL], $this->areaList->getCodes());
        foreach ($areaCodes as $areaCode) {
            $config = $this->configReader->generateCachePerScope($definitionsCollection, $areaCode);
            $config = $this->modificationChain->modify($config);

            $this->configWriter->write(
                $areaCode,
                $config
            );
        }
    }

    /**
     * Returns definitions collection
     *
     * @param string $path
     * @return DefinitionsCollection
     */
    protected function getDefinitionsCollection($path)
    {
        $definitions = new DefinitionsCollection();
        foreach ($this->areaInstancesNamesList->getList($path) as $className => $constructorArguments) {
            $definitions->addDefinition($className, $constructorArguments);
        }
        return $definitions;
    }
}
