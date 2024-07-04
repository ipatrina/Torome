# Torome

Torome is an editable URL shortener that supports shortening of URLs and short text messages.

Torome 是一款支持缩短URL和短文本的短网址程序。


![Torome preview](https://thumbs2.imgbox.com/b9/4d/qu540IOG_t.png)


# Software features / 软件功能

- Supports URL and text message shortening.

- Supports modify or delete shortened URLs.

- Supports admin login.

- English language support.

---

- 支持缩短URL和短文本。

- 支持修改和删除已创建的短网址。

- 支持管理员登录。


# Operate environment / 运行环境

- PHP 8 and above.

- MariaDB 10 or MySQL 5 and above.

---

- PHP 8 及以上版本。

- MariaDB 10 或 MySQL 5 及以上版本。

# Configurations / 配置说明

- When using for the first time, you need to edit "config.php" to connect the database, and create a table using the SQL commands.

- Depending on different web servers, you may want to create URL rewrite rules. Examples below.

---

- 首次使用时，您需要编辑“config.php”创建数据库连接，并根据该文件中的SQL指令创建数据表。

- 根据不同的网页服务器，您可能希望创建URL重写规则。示例如下。

**Apache (.htaccess)**

```
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]+)/?$ /?LinkID=$1
```

**Nginx**

```
if (!-f $request_filename) {
	set $_loc_1 f;
}
if (!-d $request_filename) {
	set $_loc_1 $_loc_1+d;
}
if ($_loc_1 = "f+d") {
	rewrite ^/([^/]+)/?$ /fwlink/?LinkID=$1;
}
rewrite / /fwlink/index.php;
```

# Changelog / 更新日志

**3.0.2 (2023/12/20)**

1.优化了一处可能会产生PHP告警的代码。

---

**3.0.1 (2023/04/21)**

1.优化了一些可能会产生PHP告警的代码。

2.配置文件中的数据库信息行增加"@"符号，防止数据库连接丢失时凭据被打印。

3.根据一年半前的用户意见，优化了源代码函数名称，采用"驼峰式命名"。

---

**3.0.0 (2021/09/25)**

1.整合网址和短消息输入框。

2.优化了网址和短消息还原的显示方式。

3.全新设计的数据库架构。

4.增强的防SQL注入设置。

5.新增独立的配置文件。

6.新增仅限管理员创建新网址的选项。
