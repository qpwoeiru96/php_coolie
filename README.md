php_coolie
==========

PHP Coolie Task Factory [PHP 苦力任务工厂]是一个以Beanstalk为后端，PHP为前端的任务分发工厂。

php_coolie 有什么优点 ?

- 完善的错误捕捉机制以及任务处理信息

	因为我们的产品需要我们非常快速的迭代，很多时候没有经过充分的测试我们的代码就上线了，所以使用任务工厂的代码如果报错一定要能被捕捉住。

	而且捕捉制度非常严格，Parse Error都能捕捉。（真实例子：传Worker代码的时候用FTP，只写入一半，提示Parse Error错误。）

	使用MySQL或者其他（需要自己实现接口）实现持久化，方便查找隐藏Bug以及优化代码。任务完成会提示执行时间以及PHP占用内存。

	![](https://raw.githubusercontent.com/qpwoeiru96/php_coolie/master/snap/1.png)

- 持久化的任务管理

	任务出错之后会推到隐藏的队列，当你想重新执行的时候，只需将这个任务踢到正常队列即可。基本上发挥了Beanstalk的优点，使用Beanstalk管理工具即可管理任务队列。
	
	![](https://raw.githubusercontent.com/qpwoeiru96/php_coolie/master/snap/2.png)

- 其他

	代各位看官们自行发现，已经用在本公司生产环境上，发现了很多隐藏的Bug也解决了很多响应问题，甚至我们将文档转换也放入其中，表现非常优异。完成了1000W+的任务！！ 

- 缺点 

	每个任务需要重新fork一个进程，所以构建上下文环境需要时间，建议将通用的上下文环境放入 Preload 配置中（比如Yii等框架的初始化）。当然这一块也是我们需要优化的地方，在第一个版本中是持久化运行，后来发现mysql后又 Connectiong Time Out的问题。所以改成了执行完一个会重新执行，当然这个会进行优化，具体优化的途径还待定。

	还有太忙了，没空写文档...


