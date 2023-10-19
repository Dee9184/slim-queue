# SMS Queue (SLIM + Beanstalkd)


Go to the project folder and run these commands : 
1. composer start
2. beanstalkd


These are the API created :

1. HTTP API to insert an SMS Message in the queue
```bash
curl --location --request POST 'http://localhost:8080/sms' \
--header 'Content-Type: application/json' \
--data '{
    "data":[
        {
            "sms" : "This is sending first message",
            "phone_no" : "01132123221"
        },
        {
            "sms" : "This is 2nd message",
            "phone_no" : "01987212312"
        },
        {
            "sms" : "This is third message",
            "phone_no" : "01987212312"
        }
    ]
}'
```


2. HTTP API to consume an SMS Message from the queue and returns it in JSON format (FIFO)
```bash
curl --location --request GET 'http://localhost:8080/sms'
```

3. HTTP API to get the total number of messages in the queue 
```bash
curl --location --request GET 'http://localhost:8080/sms/count'
```

4. HTTP API to get all SMS messages in the queue in JSON format
```
curl --location --request GET 'http://localhost:8080/sms/all'
```


