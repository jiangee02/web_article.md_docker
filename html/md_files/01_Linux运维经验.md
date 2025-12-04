# 运维经验

### 1. 服务器安全加固

- 更新系统补丁

- 配置防火墙（如iptables或ufw），只开放必要的端口（如80, 443, 22）

- **禁用root远程登录，使用密钥认证代替密码认证**

  ```bash
  #1.本地生成密钥对，公钥上传服务器
  ssh-keygen -t rsa #(-b字数 -C注释 -f 文件名)
  #当前目录会出现密钥：id_rsa,公钥:idr_rsa.pub
  #密钥放到本地主机用户家目录的.ssh文件夹中，公钥内容追加至服务器用户家目录的.ssh文件夹中的authorized_keys文件中（没有就自己创建该文件）
  #2.编辑 sshd 配置文件，通常是 /etc/ssh/sshd_config
  PermitRootLogin  no          #禁止远程登录root
  PasswordAuthentication  no   #禁止密码登录
  #3.重启服务  
  systemctl restart sshd
  ```

- **配置fail2ban来防止暴力破解**

  ```bash
  #1.安装后进入/etc/fail2ban/  复制默认配置文件本地用，
  cp jail.conf jial.local
  #2.配置jial.loacl
      [DEFAULT]
      bantime： #“拉黑时长”。默认可能是10m（10分钟）建议把它改得更长一些，比如1h或者24h
      findtime：#“观察窗口”。它配合maxretry一起工作。默认的10m意味着“在10分钟内”。
      maxretry：#“最大尝试次数”。默认可能是5。
      [sshd]
  	enabled = true
  	port = ssh #（默认22）改了的话这也改
  #3.重启服务   
  systemctl restart fail2ban
  fail2ban-client status sshd #查看具体报告
  ```



### 2. Web服务器优化

- **配置Nginx并优化性能参数**

  ```nginx
  Nginx高并发配置七步法（基于Nginx 1.18技术栈）
  user  nginx; 
  worker_processes  auto;   # 自动匹配CPU核心数，物理机设置为CPU核心数*2
  worker_rlimit_nofile 65535;  # 突破系统默认文件限制
  
  events {
      use epoll;           # Linux内核下的事件驱动模型
      worker_connections  10240; # 每个worker处理连接数，注意总和不要超过系统最大文件限制
      multi_accept on;     # 允许单次循环接受多个新连接
  }
  
  http {
      sendfile        on;     # 零拷贝技术减少内存复制
      tcp_nopush      on;     # 攒够数据包再发送
      tcp_nodelay     on;     # 禁用Nagle算法加快响应
      keepalive_timeout  60s; # 合理控制长连接时间
      keepalive_requests 1000;# 单个连接最大请求数
      ...
  }
  ```

- **设置静态资源缓存**

  进入nginx配置文件

  ```nginx
   server中加上location配置
  location ~* ^.+\.(css|js|ico|gif|jpg|jpeg|png)$ {
   log_not_found off;
   # 关闭日志
   access_log off;
   # 缓存时间7天
   expires 7d;
   add_header Cache-Control "public, immutable";  # 缓存控制头
   }
  ```

- **配置Gzip压缩**

  ```nginx
  首先在`http`模块加配置：主要是使用gzip压缩
  # 开启gzip
  gzip  on;
  # 启用gzip压缩的最小文件，小于设置值的文件将不会压缩
  gzip_min_length 1k;
  # gzip 压缩级别，1-10，数字越大压缩的越好，也越占用CPU时间。一般设置1和2
  gzip_comp_level 2;
  # 进行压缩的文件类型。javascript有多种形式。其中的值可以在 mime.types 文件中找到。
  gzip_types text/plain application/javascript application/x-javascript text/css application/xml text/javascript application/x-httpd-php image/jpeg image/gif image/png;
  # 是否在http header中添加Vary: Accept-Encoding，建议开启
  gzip_vary on;
  # 禁用IE 6 gzip
  gzip_disable "MSIE [1-6]\.";
  ```



### 3. 数据库优化

- **定期备份数据库，并测试恢复流程**

  ```bash
  #全量备份与恢复
  #使用mysql自带备份与恢复工具mysqldump
   mysqldump -u root -p passwd db_name > db_name_backup.sql #备份指定数据库
   mysqldump -u root -p passwd db_name < db_name_backup.sql #恢复指定数据库
  #增量备份（先不考虑）
  #mysql没有内置增量备份工具，但可通过二进制日志binlog（Binary Log）实现
  ```

  ```bash
  #!/bin/bash
  #自动化备份脚本
  # 设置数据库参数
  DB_USER="root"
  DB_PASSWORD="password"
  DB_NAME="db_name"
  BACKUP_DIR="/path/to/backup"
  DATE=$(date +"%Y%m%d%H%M")
  # 创建备份
  mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME > $BACKUP_DIR/${DB_NAME}_backup_$DATE.sql
  # 删除超过7天的备份
  find $BACKUP_DIR -name "*.sql" -type f -mtime +7 -exec rm {} \;
  ```

  ```bash
  chmod +x db_name_backup.sh
  #打开cron编辑器：
  crontab -e
  	#添加以下行以设置每日备份任务：
  	0 2 * * * /path/db_name_backup_.sh
  ```

- **优化数据库配置**（如innodb_buffer_pool_size）

- **学习使用慢查询日志，并优化慢查询**

```shell
#慢查询日志（Slow Query Log）是数据库记录执行时间超过预设阈值的 SQL 语句的日志系统。
```



### 4. 部署HTTPS()

- 申请免费的SSL证书（如Let's Encrypt）并配置到Web服务器
- 设置HTTP重定向到HTTPS
- **域名解析未通过暂时放弃**



### 5. 监控设置

- 安装和配置监控工具（如Prometheus + Grafana）来监控服务器资源
- 设置告警规则，当资源使用率超过阈值时发送告警

​       [Prometheus和Granafa监控方案](http://47.114.86.117:9001/article_detail.php?id=3)

### 6. 日志管理

- 配置日志轮转（logrotate）

  [Logrotate日志轮转](http://47.114.86.117:9001/article_detail.php?id=5)

- 集中管理日志，可以使用ELK栈或Graylog

### 7. 自动化部署

- 编写Shell脚本自动化部署网站（包括代码拉取、依赖安装、数据库迁移等）

  [Ansible的安装与使用](http://47.114.86.117:9001/article_detail.php?id=7)

- 学习使用CI/CD工具，如Jenkins，实现自动化测试和部署

### 8. 容器化

- 将您的网站Docker化，编写Dockerfile

- 使用Docker Compose编排多个容器

  [Docker的安装与使用](http://47.114.86.117:9001/article_detail.php?id=2)

- 学习Kubernetes，将应用部署到K8s集群

  [K8S的安装与使用](http://47.114.86.117:9001/article_detail.php?id=8)



### 9. 高可用性

- 如果条件允许，可以搭建多台服务器，配置负载均衡（如使用Nginx做负载均衡器）
- 数据库主从复制



### 10. 备份策略

- 定期备份网站文件和数据库，并上传到远程存储（如另一台服务器、云存储）
- 测试备份文件的恢复过程



### 11. 性能测试

- 学习使用性能测试工具（如Apache Bench, JMeter）对网站进行压力测试
- 根据测试结果进行优化



### 12. 文档编写

- 记录运维过程，包括服务器配置、部署步骤、故障处理等，形成文档