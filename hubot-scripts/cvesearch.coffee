# Description:
#   search cve database for vulnerabilities
#
# Notes:
# 	requries php and vFeed from https://travis-ci.org/toolswatch/vFeed
# Commands:
#   cvesearch PRODUCT - searches CVE database for a product



module.exports = (robot) ->
  robot.respond /you are a little slow/, (res) ->
   setTimeout () ->
     res.send "Who you calling 'slow'?"
   , 60 * 1000

  run_cmd = (cmd, args, cb ) ->
    console.log "spawn!"
    spawn = require("child_process").spawn
    child = spawn(cmd, args)
    child.stdout.on "data", (buffer) -> cb buffer.toString()
    child.stderr.on "data", (buffer) -> cb buffer.toString()


  robot.respond /cvesearch (.*)/i, (msg) ->
    cmd = "php/handler_cve";
    args = [ '"' + msg.match[0] + '"' ]
    run_cmd cmd, args, (text) -> msg.send text.replace("\n","");

