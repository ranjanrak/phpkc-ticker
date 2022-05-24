# phpkc-ticker
[PHP kiteconnect client](https://github.com/zerodha/phpkiteconnect) websocket ticker implementation.

## Usage
```php
$kitewebsocket = new KiteTicker("api_key", "access_token", "full", [59736071]);
$kitewebsocket->connect();
```

## To-do
* Re-connect logic
* Order update


## Response
Response structure remains the same of [current kiteconnect websocket](https://kite.trade/docs/connect/v3/websocket/#quote-packet-structure).

```
Array
(
    [tradable] => 1
    [mode] => full
    [instrument_token] => 59736071
    [last_price] => 9269
    [last_traded_quantity] => 1
    [average_traded_price] => 9503.08
    [volume_traded] => 81873
    [total_buy_quantity] => 973
    [total_sell_quantity] => 762
    [ohlc] => Array
        (
            [open] => 8750
            [high] => 9753
            [low] => 8750
            [close] => 8580
        )

    [last_trade_time] => 2022-03-07 18:45:44
    [oi] => 10930
    [oi_day_high] => 13000
    [oi_day_low] => 10857
    [exchange_timestamp] => 2022-03-07 18:45:45
    [depth] => Array
        (
            [buy] => Array
                (
                    [0] => Array
                        (
                            [quantity] => 1
                            [price] => 9267
                            [orders] => 1
                        )

                    [1] => Array
                        (
                            [quantity] => 2
                            [price] => 9266
                            [orders] => 2
                        )

                    [2] => Array
                        (
                            [quantity] => 1
                            [price] => 9265
                            [orders] => 1
                        )

                    [3] => Array
                        (
                            [quantity] => 2
                            [price] => 9264
                            [orders] => 2
                        )

                    [4] => Array
                        (
                            [quantity] => 1
                            [price] => 9263
                            [orders] => 1
                        )

                )

            [sell] => Array
                (
                    [0] => Array
                        (
                            [quantity] => 6
                            [price] => 9269
                            [orders] => 2
                        )

                    [1] => Array
                        (
                            [quantity] => 1
                            [price] => 9270
                            [orders] => 1
                        )

                    [2] => Array
                        (
                            [quantity] => 1
                            [price] => 9271
                            [orders] => 1
                        )

                    [3] => Array
                        (
                            [quantity] => 4
                            [price] => 9272
                            [orders] => 2
                        )

                    [4] => Array
                        (
                            [quantity] => 1
                            [price] => 9274
                            [orders] => 1
                        )

                )

        )
)
```
