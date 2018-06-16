<?php
namespace app\api\controller;

use Ivory\GoogleMap\Base\Coordinate;
use Ivory\GoogleMap\Service\Base\Location\CoordinateLocation;
use Ivory\GoogleMap\Service\Base\TravelMode;
use Ivory\GoogleMap\Service\Direction\DirectionService;
use Ivory\GoogleMap\Service\Direction\Request\DirectionRequest;
use Ivory\GoogleMap\Service\Direction\Response\DirectionGeocodedStatus;
use Http\Adapter\Guzzle6\Client;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use think\cache\driver\Redis;
use think\Request;

class Route extends Base
{
    /** @var DirectionService */
    private $direction;
    private $travel_mode;
    private $redis_host = 'redis';
    private $redis_port = '6379';

    public function index(Request $request)
    {
        $domain = $request->domain();
        $data = [
            'title' => "Thank's to review my application",
            'api' => [
                ['GET', $domain . '/route/{token}'],
                ['POST', $domain . '/route', [[22.619087,114.112567],[21.855444, 112.005764]]],
            ],
        ];
        return $this->sendSuccess($data);
    }

    public function save(Request $request)
    {
        $post = $request->post();
        if(empty($post)) return $this->sendError(['error' => 'Wrong input body'], false);
        foreach($post as $row) {
            if(!isset($row[0]) || !isset($row[1]) || empty(floatval($row[0])) || empty(floatval($row[1]))) {
                return $this->sendError(['error' => 'Wrong input body'], false);
            }
        }

        $token = md5($request->ip(1) . microtime(true) . uniqid());

        //$coordinate_data = [
        //    [22.619087, 114.112567],
        //    [21.855444, 112.005764],
        //    [22.993526, 113.118723],
        //    [22.970563, 113.721261],
        //];
        $data = $this->getPathAndDistance($post);

        $redis = new Redis(['host' => $this->redis_host, 'port' => $this->redis_port]);
        $rs = $redis->set($token, json_encode($data));
        if($rs == false) return $this->sendError(['error' => 'Redis set failed'], false);

        return $this->sendSuccess(['token' => $token]);
    }

    public function read($token)
    {
        if(empty($token)) return $this->sendError('Token is wrong');

        $redis = new Redis(['host' => $this->redis_host, 'port' => $this->redis_port]);
        $data = $redis->get($token);
        if(empty($data)) return $this->sendError('Redis value is wrong');
        $data = json_decode($data, true);

        return $this->sendSuccess($data);
    }

    /**
     * @author Lucas
     * @param $coordinate_data
     * @param string $sort random|order order means driving points one by one as frontend input, random picks the shortest first
     */
    private function getPathAndDistance($coordinate_data, $sort = 'random')
    {
        $this->direction = new DirectionService(new Client(), new GuzzleMessageFactory());
        $this->travel_mode = TravelMode::DRIVING;

        $start_point = array_shift($coordinate_data);
        $path_data = [
            $start_point
        ];
        $total_distance = 0;
        $total_time = 0;

        while(!empty($coordinate_data)) {
            if($sort == 'random') { // get nearest point first
                $shortest_point_info = $this->getShortestPoint($start_point, $coordinate_data);
                $key = array_search($shortest_point_info['end_point'], $coordinate_data);
                if($key === false) return $this->sendError('Can not get shortest point');
                unset($coordinate_data[$key]);
            } else { // get route in accordance with the order from frontend
                $shortest_point_info = $this->getShortestRoute($start_point, array_shift($coordinate_data));
            }

            if($shortest_point_info == false) return $this->sendError($this->getError());
            $path_data[] = $shortest_point_info['end_point'];
            $total_distance += $shortest_point_info['distance'];
            $total_time += $shortest_point_info['duration'];

            $start_point = $shortest_point_info['end_point'];
        }

        $data = [
            'path' => $path_data,
            'total_distance' => $total_distance,
            'total_time' => $total_time
        ];

        return $data;
    }

    private function getShortestPoint($start_point, $point_list) {
        $route_list = [];
        foreach($point_list as $tmp_point) {
            $route_info = $this->getShortestRoute($start_point, $tmp_point);
            if($route_info === false) return false;
            $route_list[] = $route_info;
        }

        if(empty($route_list)) return $this->setError('Route list is empty');

        array_multisort(array_column($route_list,'distance'), SORT_ASC, $route_list);
        return $route_list[0];
    }


    private function getShortestRoute($start_point, $end_point)
    {
        $request = new DirectionRequest(
            new CoordinateLocation(new Coordinate($start_point[0], $start_point[1])),
            new CoordinateLocation(new Coordinate($end_point[0],$end_point[1]))
        );
        $request->setTravelMode($this->travel_mode);
        $request->setProvideRouteAlternatives(true);
        $response = $this->direction->route($request);

        if($response->getStatus() != DirectionGeocodedStatus::OK) {
            return $this->setError($response->getStatus());
        }

        $route_info_arr = [];
        foreach($response->getRoutes() as $route) {
            foreach($route->getLegs() as $leg) {
                $tmp = [
                    'distance' => $leg->getDistance()->getValue(),
                    'duration' => $leg->getDuration()->getValue(),
                ];
                $route_info_arr[] = $tmp;
            }
        }

        if(empty($route_info_arr)) return $this->setError('Can not get a route');

        array_multisort(array_column($route_info_arr,'distance'), SORT_ASC, $route_info_arr);
        $data = $route_info_arr[0];
        $data['start_point'] = $start_point;
        $data['end_point'] = $end_point;
        return $data;
    }





}
