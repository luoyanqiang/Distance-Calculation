<?php
namespace app\api\controller;

use think\Controller;

class Base extends Controller
{

    public $error;

    public function setError($msg)
    {
        $this->error = $msg;
        return false;
    }

    public function getError()
    {
        return $this->getError();
    }

    public function sendSuccess($data, $with_status = true)
    {
        $with_status && $data['status'] = 'success';
        return json($data);
    }

    public function sendError($data, $with_status = true)
    {
        if(!is_array($data)) {
            $response['error'] = $data;
        } else {
            $response = $data;
        }
        $with_status && $response['status'] = 'failure';
        return json($response);
    }

}
