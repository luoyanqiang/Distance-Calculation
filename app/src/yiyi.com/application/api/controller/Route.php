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

    private function getPathAndDistance($coordinate_data)
    {
        $this->direction = new DirectionService(new Client(), new GuzzleMessageFactory());
        $this->travel_mode = TravelMode::DRIVING;

        $start_point = array_shift($coordinate_data);

        $path_list = [];
        foreach($this->getPermutations($coordinate_data) as $list) {
            array_unshift($list, $start_point);
            $path_list[] = $this->getPathInfo($list);
        }

        $total_distance_list = array_column($path_list, 'total_distance');
        $shortest_path = $path_list[array_search(min($total_distance_list), $total_distance_list)];

        return $shortest_path;
    }

    private function getPermutations(array $elements)
    {
        if (count($elements) <= 1) {
            yield $elements;
        } else {
            foreach ($this->getPermutations(array_slice($elements, 1)) as $permutation) {
                foreach (range(0, count($elements) - 1) as $i) {
                    yield array_merge(
                        array_slice($permutation, 0, $i),
                        [$elements[0]],
                        array_slice($permutation, $i)
                    );
                }
            }
        }
    }

    private function getPathInfo($coordinate_list)
    {
        $data = [
            'path' => [],
            'total_distance' => 0,
            'total_time' => 0,
        ];
        $coordinate_list = array_values($coordinate_list);
        $length = count($coordinate_list);
        foreach($coordinate_list as $key => $coordinate) {
            $data['path'][] = $coordinate;
            if($key == $length -1) break;
            $route_info = $this->getShortestRoute($coordinate, $coordinate_list[$key + 1]);
            $data['total_distance'] += $route_info['distance'];
            $data['total_time'] += $route_info['duration'];
        }
        return $data;
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
