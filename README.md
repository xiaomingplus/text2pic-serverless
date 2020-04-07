# text2pic 阿里云 serverless 版

最近将 scuinfo 的 text2pic 服务从之前的 [docker 版](https://github.com/xiaomingplus/scuinfoText2pic) 部署迁移到阿里云到 serverless php 环境了(因为几乎免费吧，就不用维护服务器了），开源出来供大家参考吧。

主要的迁移的难点在于我已经 5 年以上没写 php 了，频繁出现忘记写分号的错误中，已经本服务额外需要 php 扩展库 gd 库，而阿里云的官方扩展支持有限，而这个 text2pic 服务用到了 gd 库和 gd 库的依赖 freetype，需要自定义扩展，参考了网上别人的解决方案，还算是顺利，具体扩展安装见[Funfile](Funfile)

本地运行:

依赖：

- [fun](https://github.com/alibaba/funcraft)
- docker

```bash
make start
```

部署：

```bash
make deploy
```
