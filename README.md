# Introduction

Deploy Linux, Nginx, Redis, PHP7 using docker.



### Architecture

The whole app is divided into three Containers:

1. Nginx is running in `Nginx` Container, which handles requests and makes responses.
2. PHP or PHP-FPM is put in `PHP-FPM` Container, it retrieves php scripts from host, interprets, executes then responses to Nginx. If necessary, it will connect to `Redis` as well.
3. Redis lie in `Redis` Container.

### Application ###

`./app/src/yiyi.com` is an PHP application used to calculate the shortest route from specified locations


### Build and Run

    $ sudo docker-compose up

Check out your https://\<docker-host:8181\> and have fun

### Contributors

Lucas Luo <luo_iter@qq.com>
