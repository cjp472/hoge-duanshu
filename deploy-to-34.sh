#!/bin/sh
# Deploy wakeup source to specified server.

# Avoid root execution
# if [ `id|sed -e s/uid=//g -e s/\(.*//g` -eq 0 ]; then
if [ `id -u` -eq 0 ]; then
    echo "Execution by root not allowed"
    exit 1
fi

# Local source, ** If you want sync dir, don't forget the "/". **
BRANCH="./"

# Destination
HOST="192.168.210.34"
USER="root"
DEST="/m2odata/www/duanshu_api/duanshu_api"

if [ ! -d "${BRANCH}" ]; then
    echo "${BRANCH} is not exist"
    exit 1
fi

# chmod -x .php, .txt, .dll, .pem files
#for EXT in php txt xml css js ini
#do
#    echo "find . -name \"*.${EXT}\" | xargs -n 10 chmod -x"
#    find . -name "*.${EXT}" | xargs -n 10 chmod -x
#done

echo "${USER}@${HOST}:${DEST}"


rsync -avz -e "ssh -p 1212" --exclude-from="exclude.list" ${BRANCH} ${USER}@${HOST}:${DEST}

HOST1="192.168.210.30"
USER1="root"
DEST1="/m2odata/www/duanshu_pre_api"

echo "${USER1}@${HOST1}:${DEST1}"

rsync -avz -e "ssh -p 1212" --exclude-from="exclude.list" ${BRANCH} ${USER1}@${HOST1}:${DEST1}

curl -X POST -H "Content-type:application/json" -d '{"msgtype": "text","text": {"content":"短书-预发布环境部署成功"}}' https://oapi.dingtalk.com/robot/send?access_token=1cd20e0f85ac95dac148b2c1c0c152510b8529607bdc26f5a451fbf3e6b6f255