<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ImportDummy extends Module
{
    public function __construct()
    {
        $this->name = 'importdummy';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'InsiteMedia';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Import Dummy Products');
        $this->description = $this->l('Import products from Dummy.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerAdminController();
    }

    private function registerAdminController(): bool
    {
        return $this->registerTab('AdminImportDummy', $this->l('Import Dummy Products'));
    }

    private function registerTab(string $className, string $tabName): bool
    {
        $tab = new Tab();
        $tab->class_name = $className;
        $tab->module = $this->name;
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentModulesSf');
        $tab->name = [];

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = $tabName;
        }

        return $tab->add();
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function uninstall(): bool
    {
        $tabId = (int)Tab::getIdFromClassName('AdminImportDummy');
        if ($tabId) {
            $tab = new Tab($tabId);
            $tab->delete();
        }

        return parent::uninstall();
    }
}
