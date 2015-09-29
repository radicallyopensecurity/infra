# Description:
#   Returns the URL of the let-me-google|bing-that
# 
# Dependencies:
#   None
#
# Configuration:
#   None
#
# Commands:
#   hubot lmbtfy|lmgtfy me <query> 
#
# Author:
#   johnsinteur

module.exports = (robot) ->
  robot.respond /(lmbtfy)( me)? (.*)/i, (msg) ->
    msg.send "http://lmbtfy.com/?q=" + msg.match[3]
  robot.respond /(lmgtfy)( me)? (.*)/i, (msg) ->
    msg.send "http://lmgtfy.com/?q=" + msg.match[3]
