<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.easyframework.net>.
 */

namespace Easy\Mvc\Routing;

use Easy\Configure\IConfiguration;
use Easy\Core\App;
use Easy\Core\Config;
use Easy\Event\Event;
use Easy\Event\EventListener;
use Easy\Event\EventManager;
use Easy\Mvc\Controller\Controller;
use Easy\Mvc\Routing\Exception\MissingDispatcherFilterException;
use Easy\Network\Controller\ControllerResolver;
use Easy\Network\Controller\IControllerResolver;
use Easy\Network\Exception\NotFoundException;
use Easy\Network\Request;
use Easy\Network\Response;
use Easy\Rest\RestManager;
use RuntimeException;

/**
 * Dispatcher é o responsável por receber os parâmetros passados ao EasyFramework
 * através da URL, interpretá-los e direcioná-los para o respectivo controller.
 *
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @copyright Copyright 2011, EasyFramework (http://www.easy.lellysinformatica.com)
 *           
 */
class Dispatcher implements EventListener
{

    /**
     * @var EventManager Event manager, used to handle dispatcher filters
     */
    protected $eventManager;

    /**
     * @var IConfiguration The IConfiguration object wich holds the app configuration 
     */
    protected $configuration;

    /**
     * @var IControllerResolver  The IControllerResolver object
     */
    protected $resolver;

    /**
     * Constructor.
     *
     * @param IConfiguration $configuration The IConfiguration class for this app
     * @param string $base The base directory for the application. Writes `App.base` to Configure.
     */
    public function __construct(IConfiguration $configuration, IControllerResolver $resolver = null)
    {
        $this->configuration = $configuration;
        if ($resolver === null) {
            $this->resolver = new ControllerResolver();
        }
    }

    /**
     * Returns the EventManager instance or creates one if none was
     * creted. Attaches the default listeners and filters
     *
     * @return EventManager
     */
    public function getEventManager()
    {
        if (!$this->eventManager) {
            $this->eventManager = new EventManager();
            $this->eventManager->attach($this);
            $this->_attachFilters($this->eventManager);
        }
        return $this->eventManager;
    }

    /**
     * Returns the list of events this object listents to.
     * @return array
     */
    public function implementedEvents()
    {
        return array('Dispatcher.beforeDispatch' => 'parseParams');
    }

    /**
     * Attaches all event listeners for this dispatcher instance. Loads the
     * dispatcher filters from the configured locations.
     *
     * @param EventManager $manager
     * @return void
     * @throws MissingDispatcherFilterException
     */
    protected function _attachFilters($manager)
    {
        $filters = Config::read('Dispatcher.filters');
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $filter = array('callable' => $filter);
            }
            if (is_string($filter['callable'])) {
                $callable = App::classname($filter['callable'], 'Mvc\Routing\Filter');
                if (!$callable) {
                    throw new MissingDispatcherFilterException($filter['callable']);
                }
                $manager->attach(new $callable);
            } else {
                $on = strtolower($filter['on']);
                $options = array();
                if (isset($filter['priority'])) {
                    $options = array('priority' => $filter['priority']);
                }
                $manager->attach($filter['callable'], 'Dispatcher.' . $on . 'Dispatch', $options);
            }
        }
    }

    /**
     * Dispatches and invokes given Request, handing over control to the involved controller.
     * If the controller is set to autoRender, via Controller::$autoRender, then Dispatcher will render the view.
     *
     * Actions in EasyFramework can be any public method on a controller, that is not declared in
     * Controller. If you want controller methods to be public and in-accesible by URL, then prefix them with a `_`.
     * For example `public function _loadPosts() { }` would not be accessible via URL. Private and
     * protected methods are also not accessible via URL.
     *
     * If no controller of given name can be found, invoke() will throw an exception.
     * If the controller is found, and the action is not found an exception will be thrown.
     *
     * @param $request Request Request object to dispatch.
     * @param $response Response Response object to put the results of the dispatch into.
     * @param $additionalParams array Settings array ("bare", "return") which is melded with the GET and POST params
     * @return boolean Success
     * @throws MissingControllerException, MissingActionException, PrivateActionException if any of those error states are encountered.
     */
    public function dispatch(Request $request, Response $response, $additionalParams = array())
    {
        //Event
        $beforeEvent = new Event('Dispatcher.beforeDispatch', $this, compact('request', 'response', 'additionalParams'));
        $this->getEventManager()->dispatch($beforeEvent);
        $request = $beforeEvent->data['request'];
        if ($beforeEvent->result instanceof Response) {
            if (isset($request->params['return'])) {
                return $beforeEvent->result->body();
            }
            return $beforeEvent->result->send();
        }

        //Controller
        $controller = $this->resolver->getController($request, $response);

        if ($controller === false) {
            throw new NotFoundException(__('Unable to find the controller for path "%s". Maybe you forgot to add the matching route in your routing configuration?', $request->url));
        }
        
        $response = $this->_invoke($controller, $request, $response);

        if (isset($request->params['return'])) {
            return $response->body();
        }

        $afterEvent = new Event('Dispatcher.afterDispatch', $this, compact('request', 'response'));
        $this->getEventManager()->dispatch($afterEvent);
        $afterEvent->data['response']->send();
    }

    /**
     * Initializes the components and models a controller will be using.
     * Triggers the controller action, and invokes the rendering if Controller::$autoRender is true
     * and echo's the output.
     * Otherwise the return value of the controller action are returned.
     *
     * @param Controller resultoller Controller to invoke
     * @param Request resultst The request object to invoke the controller for.
     * @param Response resultnse The response object to receive the output
     * @return void
     */
    protected function _invoke(Controller $controller)
    {
        // Init the controller
        $controller->constructClasses();
        // Start the startup process
        $controller->startupProcess();
        //If the requested action is annotated with Ajax
        if ($controller->isAjax($controller->getRequest()->action)) {
            $controller->setAutoRender(false);
        }
        $manager = new RestManager($controller);
        if ($manager->isValidMethod()) {
            $result = $controller->callAction();
            $result = $manager->formatResult($result);
        } else {
            throw new RuntimeException(__("You can not access this."));
        }
        // Render the view
        if ($controller->getAutoRender()) {
            $response = $controller->display($controller->getRequest()->action);
        } else {
            $response = $controller->getResponse();
            $response->body($result);
        }
        //Send the REST response code
        $manager->sendResponseCode($response);
        // Start the shutdown process
        $controller->shutdownProcess();

        return $response;
    }

    /**
     * Applies Routing and additionalParameters to the request to be dispatched.
     * If Routes have not been loaded they will be loaded, and app/Config/routes.php will be run.
     *
     * @param $request Request Request object to mine for parameter information.
     * @param $additionalParams array An array of additional parameters to set to the request.
     *        Useful when Object::requestAction() is involved
     * @return Request The request object with routing params set.
     */
    public function parseParams($event)
    {
        $request = $event->data['request'];
        Mapper::setRequestInfo($request);

        if (empty($request->params['controller'])) {
            $params = Mapper::parse($request->url);
            $request->addParams($params);
        }

        if (!empty($event->data['additionalParams'])) {
            $request->addParams($event->data['additionalParams']);
        }
        return $request;
    }

}
