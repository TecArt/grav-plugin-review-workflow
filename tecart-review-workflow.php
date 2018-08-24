<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Plugin\TecartReviewWorkflow\JiraHelper;
use Grav\Plugin\GitSync\Helper as GitHelper;


class TecartReviewWorkflowPlugin extends Plugin
{    
    protected $route = 'reviews';
    
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized'              => ['onPluginsInitialized', 0],
            'onAdminTwigTemplatePaths'          => ['onAdminTwigTemplatePaths', 0],
            'onGetPageTemplates'                => ['onGetPageTemplates', 0]
        ];
    }

    public function onPluginsInitialized()
    {
        $this->grav['locator']->addPath('blueprints', '', __DIR__ . DS . 'blueprints');

        require_once __DIR__ . '/vendor/autoload.php';

        if ($this->isAdmin()) {

            $this->enable([
                'onTwigTemplatePaths'           => ['onTwigTemplatePaths', 1],
                'onAdminCreatePageFrontmatter'  => ['onAdminCreatePageFrontmatter', 0],
                'onAdminSave'                   => ['onAdminSave', 10],
                'onAdminMenu'                   => ['onAdminMenu', 1]
            ]);
        }
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }

    public function onAdminTwigTemplatePaths($event)
    {
        $event['paths'] = [__DIR__ . '/admin/themes/grav/templates'];
    }

    public function onGetPageTemplates(Event $event)
    {
        $types = $event->types;
        $locator = Grav::instance()['locator'];
        $types->scanBlueprints($locator->findResource('plugin://' . $this->name . '/blueprints'));
        $types->scanBlueprints($locator->findResource('theme://review'));
    }

    public function onAdminMenu() {
        $this->grav['twig']->plugins_hooked_nav['Reviews'] = ['route' => $this->route, 'icon' => 'fa-eye'];
    }

    public function onAdminCreatePageFrontmatter(Event $event) {

        $header = $event['header'];
        $param  = $event['data'];

        // Set Jira Variables
        $jira_url     = $this->config->get('plugins.tecart-jira-connector.jira_url');
        $jira_user    = $this->config->get('plugins.tecart-jira-connector.jira_user');
        $enc_password = $this->config->get('plugins.tecart-jira-connector.jira_password');

        $password     = GitHelper::decrypt($enc_password);
        $userpwd      = $jira_user . ":" . $password;

        $issues       = isset($this->grav['twig']->issues) ? $this->grav['twig']->issues : null;
        $issue_id     = isset($param['issue_id']) ? $param['issue_id'] : null;

        $user         = $this->grav['user']['username'];
        $userfull     = $this->grav['user']['fullname'];
        
        // Prepare new page
        $header['published'] = false;
        $header['draft']['title'] = $header['title'];
        $header['draft']['transition'] = 'new';
        $header['draft']['last_modified_by'] = $user;
        $header['draft']['issue']['id'] = $issue_id;

        // Save Issue Summary
        if ($issues && $issue_id) {
            foreach ($issues as $issue ) 
                if ($issue['key'] == $issue_id) 
                    $header['draft']['issue']['summary'] = $issue['fields']['summary'];
        }

        // Write page data
        $event['header'] = $header;

        // Set Twig Transition Vaiable
        $this->grav['twig']->transition = isset($transition) ? $transition : false;

        // Start progress in jira issue
        $transition_id = "4";
        $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
        $jira_data = array ("transition" => array ("id" => $transition_id));
        $out = JiraHelper::postRestAPI($transition_url, $jira_data, $userpwd);
    }

    public function onAdminSave($event)
    {
        $obj   = $event['object'];
        $param = $event['data'];
        $uri   = $this->grav['uri'];

        if (isset($uri->paths()[2]) && $uri->paths()[1] === 'pages')
        {  
            // Save Transition
            $transition   = isset($param['fireTransition']) ? $param['fireTransition'] : '';
            $obj->header()->draft['transition'] = $transition;
            $this->grav['twig']->transition = isset($transition) ? $transition : false;

            // Set Jira Variables
            $jira_url     = $this->config->get('plugins.tecart-jira-connector.jira_url');
            $jira_user    = $this->config->get('plugins.tecart-jira-connector.jira_user');
            $enc_password = $this->config->get('plugins.tecart-jira-connector.jira_password');
            $issues       = isset($this->grav['twig']->issues) ? $this->grav['twig']->issues : null;

            $password     = GitHelper::decrypt($enc_password);
            $userpwd      = $jira_user . ":" . $password;
            $user         = $this->grav['user']['username'];

            // Execute transition
            switch ($transition) {
            case 'reset':
                
                $issue_id = isset($obj->header()->draft['issue_id']) ? $obj->header()->draft['issue_id'] : null;

                // Stop Review or Progress
                $transition_id = $this->config->get('plugins.tecart-review-workflow.stop_progress');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id));
                
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                unset($obj->header()->draft);
                $this->grav['admin']->setMessage('Reset erfolgreich', 'info');

                break;

            case 'edit':

                $issue_id = isset($param['issue_id']) ? $param['issue_id'] : null;
                $obj->header()->draft['issue']['id'] = $issue_id;

                // Prepare draft mode
                $obj->header()->draft['content']          = $obj->rawMarkdown();
                $obj->header()->draft['title']  = isset($obj->header()->title) ? $obj->header()->title : '';
                $obj->header()->draft['last_modified_by'] = $user;

                // Handle Content Variables
                if (isset($obj->header()->review)) {
                    $obj->header()->draft['review'] = $obj->header()->review;
                }

                // Save Issue Summary
                if ($issues && $issue_id) {
                    foreach ($issues as $issue ) 
                        if ($issue['key'] == $issue_id) 
                            $obj->header()->draft['issue']['summary'] = $issue['fields']['summary'];
                }
                
                // Start progress
                $transition_id = $this->config->get('plugins.tecart-review-workflow.start_progress');;
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id));
                
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                $this->grav['admin']->setMessage('Die Seite befindet sich im Bearbeitungsmodus und ist von anderen Nutzern nicht editierbar.', 'info');
                
                break;

            case 'commit':
            
                $obj->header()->draft['last_modified_by'] = $user;

                // Stop Progress
                $issue_id = isset($obj->header()->draft['issue']['id']) ? $obj->header()->draft['issue']['id'] : null;
                $transition_id  = "301";
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data           = array ("transition" => array ("id" => $transition_id));

                JiraHelper::postRestAPI($transition_url, $data, $userpwd);
            
                $this->grav['admin']->setMessage('Änderungen wurden auf dem Server gespeichert', 'info');

                break;

            case 'continue':
                
                $issue_id = isset($param['issue_id']) ? $param['issue_id'] : null;
                $obj->header()->draft['last_modified_by'] = $user;
                $obj->header()->draft['issue']['id'] = $issue_id;

                // Save Issue Summary
                if ($issues && $issue_id) {
                    foreach ($issues as $issue ) 
                        if ($issue['key'] == $issue_id) 
                            $obj->header()->draft['issue']['summary'] = $issue['fields']['summary'];
                }
                
                // Start progress
                $transition_id = $this->config->get('plugins.tecart-review-workflow.start_progress');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id));

                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                $this->grav['admin']->setMessage('Die Seite befindet sich im Bearbeitungsmodus und ist von anderen Nutzern nicht editierbar.', 'info');
                
                break;

            case 'review':

                $issue_id = isset($obj->header()->draft['issue']['id']) ? $obj->header()->draft['issue']['id'] : null;
                $assignee = isset($param['assignee']) ? $param['assignee'] : false;
                
                $obj->header()->draft['last_modified_by'] = $user;
                $obj->header()->draft['assignee'] = $assignee;
                
                if ($assignee) {
                    $data = array ("fields" => array ("assignee" => array ("name" => $assignee)));
                    $issue_message = isset($param['issue_message']) ? $param['issue_message'] : false;
                    if ($issue_message) {
                        $obj->header()->draft['issue']['message'] = $issue_message;
                        $data["update"] = array (
                            "comment" => array (0 => array (
                                "add" => array (
                                    // "author" => array(
                                    //     "name" => $user
                                    // ),                                    
                                    "body" => $issue_message
                                )
                            )
                        ));
                    }
                    $url = $jira_url . "/rest/api/2/issue/" . $issue_id;
                    JiraHelper::putRestAPI($url, $data, $userpwd);
                };

                // Start Review
                $transition_id  = $this->config->get('plugins.tecart-review-workflow.start_review');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data           = array ("transition" => array ("id" => $transition_id));
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);
            
                $this->grav['admin']->setMessage('Änderungen wurde zur Überprüfung weitergeleitet.', 'info');

                break;

            case 'decline':

                // Prepare draft mode
                $issue_id = isset($obj->header()->draft['issue']['id']) ? $obj->header()->draft['issue']['id'] : null;
                unset($obj->header()->draft['assignee']);

                // Update Assignee and add Comment
                $modified_by  = $obj->header()->draft['last_modified_by'] ?? null;
                $data = array ("fields" => array ("assignee" => array ("name" => $modified_by)));
                $issue_message = $param['issue_message'] ?? null;
                if ($issue_message) {
                    $obj->header()->draft['issue']['message'] = $issue_message;
                    $data['update'] = array (
                        "comment" => array (0 => array (
                            "add" => array (
                                // "author" => array(
                                //     "name" => $user
                                // ),
                                "body" => $issue_message
                            )
                        )
                    ));
                }
                $url = $jira_url . "/rest/api/2/issue/" . $issue_id;
                JiraHelper::putRestAPI($url, $data, $userpwd);

                // Stop Review
                $transition_id = $this->config->get('plugins.tecart-review-workflow.stop_review');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id));
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                $this->grav['admin']->setMessage('Änderungen wurde abgelehnt.', 'info');

                break;

            case 'publish':
            
                $issue_id = isset($obj->header()->draft['issue']['id']) ? $obj->header()->draft['issue']['id'] : null;
                $modified_by  = $obj->header()->draft['last_modified_by'] ?? null;

                // Set new Content and Title
                $obj->content(isset($obj->header()->draft['content']) ? $obj->header()->draft['content'] : null);
                $obj->header()->title = isset($obj->header()->draft['title']) ? $obj->header()->draft['title'] : null;

                // Handle Modular Content
                if (isset($obj->header()->draft['review'])) {
                    $obj->header()->review = $obj->header()->draft['review'];
                }

                unset($obj->header()->draft);
                unset($obj->header()->published);

                // Start QA Review
                $transition_id_stop = $this->config->get('plugins.tecart-review-workflow.start_qa');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id_stop));
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                // Publish (done)
                $transition_id_stop = $this->config->get('plugins.tecart-review-workflow.publish');
                $transition_url = $jira_url . "/rest/api/2/issue/" . $issue_id . '/transitions?expand=transitions.fields';
                $data = array ("transition" => array ("id" => $transition_id_stop));
                JiraHelper::postRestAPI($transition_url, $data, $userpwd);

                // Issue back to user last_modified_by
                $data = array ("fields" => array ("assignee" => array ("name" => $modified_by)));
                $url = $jira_url . "/rest/api/2/issue/" . $issue_id;
                JiraHelper::putRestAPI($url, $data, $userpwd);

                $this->grav['admin']->setMessage('Review erfolgreich', 'info');

                break;

            default: // read-only
            
                $obj->header()->draft['last_modified_by'] = $user;

                break;
            }
        }

        return $obj;
    }
}
