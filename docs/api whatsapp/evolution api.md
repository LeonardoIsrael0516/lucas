Get Information
Get information about your EvolutionAPI

GET

Try it
Path Parameters
​
instance
stringrequired
ID of the instance to connect

Response
200 - application/json
Ok

​
status
integer
The HTTP status of the response

​
message
string
Descriptive message about the current state of the API

​
version
string
The current version of the API

​
swagger
string
URL to the API's Swagger documentation

​
manager
string
URL to the API manager

​
documentation
string
URL to the detailed API documentation

Create Instance

Get information about your EvolutionAPI

curl --request GET \
  --url https://evolution-example/

200
{
  "status": 200,
  "message": "Welcome to the Evolution API, it is working!",
  "version": "1.7.4",
  "swagger": "http://example.evolution-api.com/docs",
  "manager": "http://example.evolution-api.com/manager",
  "documentation": "https://doc.evolution-api.com"
}

Set Webhook
Set Webhook for instance

POST
/
webhook
/
set
/
{instance}

Try it
Authorizations
​
apikey
stringheaderrequired
Your authorization key header

Path Parameters
​
instance
stringrequired
Name of the instance

Body
application/json
​
enabled
booleanrequired
enable webhook to instance

​
url
stringrequired
Webhook URL

​
webhookByEvents
booleanrequired
Enables Webhook by events

​
webhookBase64
booleanrequired
Sends files in base64 when available

​
events
enum<string>[]required
Events to be sent to the Webhook

Minimum array length: 1
Available options: APPLICATION_STARTUP, QRCODE_UPDATED, MESSAGES_SET, MESSAGES_UPSERT, MESSAGES_UPDATE, MESSAGES_DELETE, SEND_MESSAGE, CONTACTS_SET, CONTACTS_UPSERT, CONTACTS_UPDATE, PRESENCE_UPDATE, CHATS_SET, CHATS_UPSERT, CHATS_UPDATE, CHATS_DELETE, GROUPS_UPSERT, GROUP_UPDATE, GROUP_PARTICIPANTS_UPDATE, CONNECTION_UPDATE, CALL, NEW_JWT_TOKEN, TYPEBOT_START, TYPEBOT_CHANGE_STATUS 
Response
201 - application/json
Created

​
webhook
object
Show child attributes

Set Presence
Find Webhook
website
github
Powered by
curl --request POST \
  --url https://evolution-example/webhook/set/{instance} \
  --header 'Content-Type: application/json' \
  --header 'apikey: <api-key>' \
  --data '
{
  "enabled": true,
  "url": "<string>",
  "webhookByEvents": true,
  "webhookBase64": true,
  "events": [
    "APPLICATION_STARTUP"
  ]
}
'

201
{
  "webhook": {
    "instanceName": "teste-docs",
    "webhook": {
      "url": "https://example.com",
      "events": [
        "APPLICATION_STARTUP"
      ],
      "enabled": true
    }
  }
}
https://doc.evolution-api.com/v2/api-reference/message-controller/send-text