<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Pheanstalk\Pheanstalk;


return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    $app->get('/sms', function (Request $request, Response $response) {

        try{
            $pheanstalk = Pheanstalk::create($_ENV['BEANSTALKD_IP_ADDRESS']);
            $pheanstalk->watch(new \Pheanstalk\Values\TubeName('sms_tube'));  
            
            $job = $pheanstalk->reserveWithTimeout(3);
            
            if(empty($job)){
                $getJob = 'No SMS(s) found';
            }else{
                $getJob = $job->getData();
                $pheanstalk->delete($job);
            }
            
            $response->getBody()->write($getJob);
        }catch(Exception $e){
            $response->getBody()->write($e->getMessage());
        }


        return $response;
    });

    $app->get('/sms/count', function (Request $request, Response $response) {
    
        try{
            $pheanstalk = Pheanstalk::create($_ENV['BEANSTALKD_IP_ADDRESS']);
            $pheanstalk->watch(new \Pheanstalk\Values\TubeName('sms_tube'));     
    
            $cntJobs = 0;
            while ($job = $pheanstalk->reserveWithTimeout(3)) {
                $cntJobs += 1;
            }

            $response->getBody()->write(json_encode($cntJobs) . ' SMS(s) found');
            
        }catch(Exception $e){
            $response->getBody()->write($e->getMessage());
        }
        
         
        return $response;   
    });

    $app->get('/sms/all', function (Request $request, Response $response) {
        
        try{
            $pheanstalk = Pheanstalk::create($_ENV['BEANSTALKD_IP_ADDRESS']);
            $pheanstalk->watch(new \Pheanstalk\Values\TubeName('sms_tube'));     
    
            $jobs = array();
            while ($job = $pheanstalk->reserveWithTimeout(3)) {
                $jobs[] = $job->getData();
                
                $pheanstalk->delete($job);
            }

            if(empty($jobs)){
                $jobs = 'No SMS(s) found';
            }

            $response->getBody()->write(json_encode($jobs));
            
        }catch(Exception $e){
            $response->getBody()->write($e->getMessage());
        }
        
         
        return $response;      
    });

    $app->post('/sms', function (Request $request, Response $response) {
    
        try{
            $reqData = $request->getParsedBody();
            $cntFailed = 0;
            $errMessage = '';
            $getErrMessage = array();
            $jobIds = array();
            foreach($reqData['data'] as $smsData){
                
                if(empty($smsData['phone_no'])){
                    $errMessage = 'Missing phone number';
                }else if(empty($smsData['sms'])){
                    $errMessage = 'Missing SMS message';
                }
                
                if(!empty($errMessage)){
                    $getErrMessage[] = $errMessage;
                    $errMessage = '';
                }else{
                    $pheanstalk = Pheanstalk::create($_ENV['BEANSTALKD_IP_ADDRESS']);
                    
                    // Put a job into the queue
                    $pheanstalk->useTube(new \Pheanstalk\Values\TubeName('sms_tube'));
                    $jobId = $pheanstalk->put($smsData['phone_no'].' : '.$smsData['sms']);
                    
                    $jobIds[] = $jobId->getId();
                }
            }       
            
            if(!empty($getErrMessage)){
                $msg = implode(' and ', array_unique($getErrMessage));
                $response->getBody()->write(count($getErrMessage).' '. $msg .'. ');
            }
            
            if(!empty($jobIds)){
                $jobIds = implode(', ' , $jobIds);
                $response->getBody()->write('SMS(s) successfully added with ID : '. $jobIds);
            }
        }catch(Exception $e){
            $response->getBody()->write($e->getMessage());
        }

        return $response;
    });
};
