<?php
namespace Omeka\Controller\SiteAdmin;

use Omeka\Event\Event;
use Omeka\Form\ConfirmForm;
use Omeka\Form\SiteForm;
use Omeka\Form\SitePageForm;
use Omeka\Form\SiteSettingsForm;
use Omeka\Site\Navigation\Link\Manager as LinkManager;
use Omeka\Site\Navigation\Translator;
use Omeka\Site\Theme\Manager as ThemeManager;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var ThemeManager
     */
    protected $themes;

    /**
     * @var LinkManager
     */
    protected $navLinks;

    /**
     * @var Translator
     */
    protected $navTranslator;

    public function __construct(ThemeManager $themes, LinkManager $navLinks,
        Translator $navTranslator
    ) {
        $this->themes = $themes;
        $this->navLinks = $navLinks;
        $this->navTranslator = $navTranslator;
    }

    public function indexAction()
    {
        $this->setBrowseDefaults('title');
        $response = $this->api()->search('sites', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('sites', $response->getContent());
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(SiteForm::class);
        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $formData['o:item_pool'] = json_decode($formData['item_pool'], true);
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api()->create('sites', $formData);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('Site created.');
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function editAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SiteForm::class);
        $form->setData($site->jsonSerialize());

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api()->update('sites', $site->id(), $formData, [], true);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('Site updated.');
                    // Explicitly re-read the site URL instead of using
                    // refresh() so we catch updates to the slug
                    return $this->redirect()->toUrl($site->url());
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function settingsAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SiteSettingsForm::class);

        $event = new Event(Event::SITE_SETTINGS_FORM, $this, ['form' => $form]);
        $this->getEventManager()->trigger($event);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $data = $form->getData();
                unset($data['csrf']);
                foreach ($data as $id => $value) {
                    $this->siteSettings()->set($id, $value);
                }
                $this->messenger()->addSuccess('Settings updated.');
                return $this->redirect()->refresh();
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function addPageAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SitePageForm::class);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $formData['o:site']['o:id'] = $site->id();
                $response = $this->api()->create('site_pages', $formData);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('Page created.');
                    return $this->redirect()->toRoute(
                        'admin/site/page',
                        ['action' => 'index'],
                        true
                    );
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function navigationAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $jstree = json_decode($formData['jstree'], true);
            $formData['o:navigation'] = $this->navTranslator->fromJstree($jstree);
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api()->update('sites', $site->id(), $formData, [], true);
                if (!$response->isError()) {
                    $this->messenger()->addSuccess('Navigation updated.');
                    return $this->redirect()->refresh();
                }
                $this->messenger()->addErrors($response->getErrors());
            }
        }

        $view = new ViewModel;
        $view->setVariable('navTree', $this->navTranslator->toJstree($site));
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        return $view;
    }

    public function itemPoolAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $formData['o:item_pool'] = json_decode($formData['item_pool'], true);
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api()->update('sites', $site->id(), $formData, [], true);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('Item pool updated.');
                    return $this->redirect()->refresh();
                }
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function usersAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api()->update('sites', $site->id(), $formData, [], true);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $this->messenger()->addSuccess('User permissions updated.');
                    return $this->redirect()->refresh();
                }
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $users = $this->api()->search('users', ['sort_by' => 'name']);

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('users', $users->getContent());
        return $view;
    }

    public function themeAction()
    {
        $site = $this->currentSite();

        $theme = $this->themes->getTheme($site->theme());
        $config = $theme->getConfigSpec();

        $view = new ViewModel;
        if (!($config && $config['elements'])) {
            return $view;
        }

        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        foreach ($config['elements'] as $elementSpec) {
            $form->add($elementSpec);
        }

        $oldSettings = $this->siteSettings()->get($theme->getSettingsKey());
        if ($oldSettings) {
            $form->setData($oldSettings);
        }

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $data = $form->getData();
                unset($data['form_csrf']);
                $this->siteSettings()->set($theme->getSettingsKey(), $data);
                $this->messenger()->addSuccess('Theme settings updated.');
                return $this->redirect()->refresh();
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }
        $view->setVariable('form', $form);
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api()->delete('sites', [
                    'slug' => $this->params('site-slug')]
                );
                if ($response->isError()) {
                    $this->messenger()->addError('Site could not be deleted');
                } else {
                    $this->messenger()->addSuccess('Site successfully deleted');
                }
            } else {
                $this->messenger()->addError('Site could not be deleted');
            }
        }
        return $this->redirect()->toRoute('admin/site');
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $view = new ViewModel;
        $view->setVariable('site', $site);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $site = $this->currentSite();
        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resourceLabel', 'site');
        $view->setVariable('partialPath', 'omeka/site-admin/index/show-details');
        $view->setVariable('resource', $site);
        return $view;
    }

    public function navigationLinkFormAction()
    {
        $site = $this->currentSite();
        $link = $this->navLinks->get($this->params()->fromPost('type'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate($link->getFormTemplate());
        $view->setVariable('data', $this->params()->fromPost('data'));
        $view->setVariable('site', $site);
        $view->setVariable('link', $link);
        return $view;
    }

    public function sidebarItemSelectAction()
    {
        $this->setBrowseDefaults('created');
        $site = $this->currentSite();

        $itemPool = is_array($site->itemPool()) ? $site->itemPool() : [];
        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('items', $query);
        $items = $response->getContent();
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('omeka/admin/item/sidebar-select');
        $value = $this->params()->fromQuery('value');
        $view->setVariable('searchValue', $value ? $value['in'][0] : '');
        $view->setVariable('items', $items);
        $view->setVariable('showDetails', false);
        return $view;
    }
}
