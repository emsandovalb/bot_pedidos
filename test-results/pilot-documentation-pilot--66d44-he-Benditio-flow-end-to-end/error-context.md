# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: pilot-documentation.spec.js >> pilot documentation package captures the Benditio flow end to end
- Location: tests\e2e\pilot-documentation.spec.js:15:1

# Error details

```
Test timeout of 120000ms exceeded.
```

```
Error: page.waitForResponse: Test timeout of 120000ms exceeded.
```

# Page snapshot

```yaml
- generic [ref=f3e4]:
  - complementary [ref=f3e5]:
    - link [ref=f3e8] [cursor=pointer]:
      - /url: http://127.0.0.1:8010/dashboard
      - img "Benditio" [ref=f3e10]
    - navigation [ref=f3e11]:
      - link "Panel" [ref=f3e12] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/dashboard
      - link "Analítica" [ref=f3e16] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/analytics
      - link "Operaciones" [ref=f3e20] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/operations
      - link "Modo simple" [ref=f3e24] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/operations/focus
      - link "Pedidos" [ref=f3e28] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/orders
      - link "Revisión de pedidos" [ref=f3e32] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/order-reviews
      - link "Clientes" [ref=f3e36] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/customers
      - link "Catálogo de productos" [ref=f3e40] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/products
      - link "Sucursales" [ref=f3e44] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/branches
      - link "Cierres diarios" [ref=f3e48] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/daily-order-closures
      - link "Bandeja de mensajes" [ref=f3e52] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/incoming-messages
      - generic [ref=f3e56]:
        - generic [ref=f3e57]: Channels
        - generic [ref=f3e58]:
          - link "WhatsApp Setup" [ref=f3e59] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/whatsapp
            - generic [ref=f3e62]: WhatsApp
            - generic [ref=f3e63]: Setup
          - link "Telegram" [ref=f3e64] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/telegram
          - link "Instagram Soon" [ref=f3e68] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/instagram
            - generic [ref=f3e71]: Instagram
            - generic [ref=f3e72]: Soon
      - generic [ref=f3e73]:
        - generic [ref=f3e74]: Configuracion
        - link "Notificaciones" [ref=f3e76] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/settings/notifications
      - generic [ref=f3e80]:
        - generic [ref=f3e81]: Soporte
        - link "Configuraciones" [ref=f3e83] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/setup-requests
      - generic [ref=f3e87]:
        - generic [ref=f3e88]: Developer
        - link "Developer Toolkit" [ref=f3e90] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/developer/webhook-simulator
    - generic [ref=f3e96]:
      - generic [ref=f3e97]: Owner User
      - generic [ref=f3e98]: owner@local.test
      - generic [ref=f3e99]:
        - generic [ref=f3e100]: Activo
        - generic [ref=f3e101]: owner
      - button "Cerrar sesión" [ref=f3e103] [cursor=pointer]
  - generic [ref=f3e104]:
    - banner [ref=f3e105]:
      - generic [ref=f3e106]:
        - generic [ref=f3e108]:
          - generic [ref=f3e109]: Owner User
          - generic [ref=f3e110]: owner
        - generic [ref=f3e111]:
          - generic [ref=f3e112]: Sep 03, 2026
          - link "Pedidos pendientes" [ref=f3e113] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/operations
    - main [ref=f3e114]:
      - generic [ref=f3e116]:
        - generic [ref=f3e118]:
          - generic [ref=f3e119]:
            - generic [ref=f3e120]:
              - generic [ref=f3e121]: Developer only
              - heading "Business Scenario Simulator" [level=1] [ref=f3e122]
              - paragraph [ref=f3e123]: Local business day simulator for QA, demonstrations and operational testing. It reuses the real ingestion path and stays hidden outside local or debug environments.
            - generic [ref=f3e124]:
              - generic [ref=f3e125]:
                - generic [ref=f3e126]: Provider
                - generic [ref=f3e127]: WhatsApp
              - generic [ref=f3e128]:
                - generic [ref=f3e129]: Phone Number ID
                - generic [ref=f3e130]: PHONE_NUMBER_ID_TEST
              - generic [ref=f3e131]:
                - generic [ref=f3e132]: Mode
                - generic [ref=f3e133]: Local / Debug
          - generic [ref=f3e134]:
            - generic [ref=f3e135]:
              - generic [ref=f3e136]:
                - generic [ref=f3e137]: Result
                - generic [ref=f3e138]: Custom message injected through the production pipeline.
                - generic [ref=f3e140]:
                  - generic [ref=f3e141]: Execution
                  - generic [ref=f3e142]: 116 ms
              - generic [ref=f3e145]:
                - generic [ref=f3e146]:
                  - generic [ref=f3e147]: Orders
                  - generic [ref=f3e148]: "1"
                - generic [ref=f3e149]:
                  - generic [ref=f3e150]: Customers
                  - generic [ref=f3e151]: "1"
                - generic [ref=f3e152]:
                  - generic [ref=f3e153]: WhatsApp
                  - generic [ref=f3e154]: "1"
                - generic [ref=f3e155]:
                  - generic [ref=f3e156]: Telegram
                  - generic [ref=f3e157]: "0"
                - generic [ref=f3e158]:
                  - generic [ref=f3e159]: Duplicates
                  - generic [ref=f3e160]: "0"
                - generic [ref=f3e161]:
                  - generic [ref=f3e162]: VIP / recurrent
                  - generic [ref=f3e163]: "0"
              - generic [ref=f3e164]:
                - generic [ref=f3e165]:
                  - generic [ref=f3e166]: Customers
                  - generic [ref=f3e167]: "29"
                - generic [ref=f3e168]:
                  - generic [ref=f3e169]: Delivery / Pickup
                  - generic [ref=f3e170]: 143 / 45
                - generic [ref=f3e171]:
                  - generic [ref=f3e172]: Today / Tomorrow
                  - generic [ref=f3e173]: 34 / 15
                - generic [ref=f3e174]:
                  - generic [ref=f3e175]: Urgent / Duplicates
                  - generic [ref=f3e176]: 116 / 119
                - generic [ref=f3e177]:
                  - generic [ref=f3e178]: Avg parser confidence
                  - generic [ref=f3e179]: "0.74"
                - generic [ref=f3e180]:
                  - generic [ref=f3e181]: Avg priority score
                  - generic [ref=f3e182]: "72.98"
                - generic [ref=f3e183]:
                  - generic [ref=f3e184]: Avg SLA
                  - generic [ref=f3e185]: "263.73"
                - generic [ref=f3e186]:
                  - generic [ref=f3e187]: Execution time
                  - generic [ref=f3e188]: 116 ms
            - generic [ref=f3e189]:
              - link "View latest order" [ref=f3e190] [cursor=pointer]:
                - /url: http://127.0.0.1:8010/orders/1221
              - link "View latest incoming message" [ref=f3e191] [cursor=pointer]:
                - /url: http://127.0.0.1:8010/incoming-messages#incoming-message-1222
        - generic [ref=f3e192]:
          - generic [ref=f3e193]:
            - generic [ref=f3e194]:
              - generic [ref=f3e195]:
                - generic [ref=f3e196]:
                  - heading "Custom Ingestion Playground" [level=2] [ref=f3e197]
                  - paragraph [ref=f3e198]: Switch provider and the payload shape, required fields and examples update automatically.
                - generic [ref=f3e199]:
                  - generic [ref=f3e200]: Phone Number ID
                  - generic [ref=f3e201]: Customer name
                  - generic [ref=f3e202]: Customer phone
                  - generic [ref=f3e203]: Message ID
                  - generic [ref=f3e204]: Message text
              - generic [ref=f3e205]:
                - button "Pedido simple" [ref=f3e206] [cursor=pointer]
                - button "Pedido multiple" [ref=f3e207] [cursor=pointer]
                - button "Mensaje duplicado" [ref=f3e208] [cursor=pointer]
                - button "Payload de estado" [ref=f3e209] [cursor=pointer]
              - generic [ref=f3e210]:
                - generic [ref=f3e211]:
                  - generic [ref=f3e212]:
                    - text: Provider
                    - combobox "Provider" [ref=f3e213]:
                      - option "WhatsApp" [selected]
                      - option "Telegram"
                      - option "Instagram"
                  - generic [ref=f3e214]:
                    - text: Phone Number ID
                    - textbox "Phone Number ID" [ref=f3e215]: PHONE_NUMBER_ID_TEST
                - generic [ref=f3e216]:
                  - generic [ref=f3e217]:
                    - text: Customer name
                    - textbox "Customer name" [ref=f3e218]: Maria Lopez
                  - generic [ref=f3e219]:
                    - text: Customer phone
                    - textbox "Customer phone" [ref=f3e220]: "50255510001"
                - generic [ref=f3e221]:
                  - generic [ref=f3e222]:
                    - text: Message id
                    - textbox "Message id" [ref=f3e223]: wamid.simulator-001
                  - generic [ref=f3e224]:
                    - text: Message text
                    - textbox "Message text" [ref=f3e225]: 2 bolsas de jardin
                - group [ref=f3e226]:
                  - generic "Payload preview Generated from the form" [ref=f3e227] [cursor=pointer]
                  - textbox [ref=f3e228]: "{ \"object\": \"whatsapp_business_account\", \"entry\": [ { \"id\": \"entry-simulator-1\", \"changes\": [ { \"field\": \"messages\", \"value\": { \"messaging_product\": \"whatsapp\", \"metadata\": { \"display_phone_number\": \"+50255550000\", \"phone_number_id\": \"PHONE_NUMBER_ID_TEST\" }, \"contacts\": [ { \"profile\": { \"name\": \"Maria Lopez\" }, \"wa_id\": \"50255510001\" } ], \"messages\": [ { \"from\": \"50255510001\", \"id\": \"wamid.simulator-001\", \"timestamp\": \"1788454559\", \"type\": \"text\", \"text\": { \"body\": \"2 bolsas de jardin\" } } ] } } ] } ] }"
                - generic [ref=f3e229]:
                  - button "Send simulated webhook" [ref=f3e230] [cursor=pointer]
                  - button "Restore from form" [ref=f3e231] [cursor=pointer]
            - generic [ref=f3e232]:
              - generic [ref=f3e233]:
                - generic [ref=f3e234]:
                  - heading "Business Scenarios" [level=2] [ref=f3e235]
                  - paragraph [ref=f3e236]: Generate realistic business days through the live ingestion path, then inspect the resulting metrics.
                - generic [ref=f3e237]:
                  - generic [ref=f3e238]: Real ingestion
                  - generic [ref=f3e239]: Live queue
                  - generic [ref=f3e240]: Operations agenda
              - generic [ref=f3e241]:
                - generic [ref=f3e242]:
                  - generic [ref=f3e243]:
                    - generic [ref=f3e244]:
                      - generic [ref=f3e245]: Small Hardware Store
                      - generic [ref=f3e246]: 30 realistic orders from a mixed hardware store day.
                    - button "Generate Day" [ref=f3e247] [cursor=pointer]
                  - generic [ref=f3e248]:
                    - generic [ref=f3e249]:
                      - term [ref=f3e250]: Customers
                      - definition [ref=f3e251]: "15"
                    - generic [ref=f3e252]:
                      - term [ref=f3e253]: Orders
                      - definition [ref=f3e254]: "30"
                    - generic [ref=f3e255]:
                      - term [ref=f3e256]: WhatsApp
                      - definition [ref=f3e257]: "20"
                    - generic [ref=f3e258]:
                      - term [ref=f3e259]: Telegram
                      - definition [ref=f3e260]: "10"
                - generic [ref=f3e261]:
                  - generic [ref=f3e262]:
                    - generic [ref=f3e263]:
                      - generic [ref=f3e264]: Morning Rush
                      - generic [ref=f3e265]: 15 delivery-heavy orders before noon, mostly urgent and for today.
                    - button "Generate Day" [ref=f3e266] [cursor=pointer]
                  - generic [ref=f3e267]:
                    - generic [ref=f3e268]:
                      - term [ref=f3e269]: Customers
                      - definition [ref=f3e270]: "8"
                    - generic [ref=f3e271]:
                      - term [ref=f3e272]: Orders
                      - definition [ref=f3e273]: "15"
                    - generic [ref=f3e274]:
                      - term [ref=f3e275]: WhatsApp
                      - definition [ref=f3e276]: "10"
                    - generic [ref=f3e277]:
                      - term [ref=f3e278]: Telegram
                      - definition [ref=f3e279]: "5"
                - generic [ref=f3e280]:
                  - generic [ref=f3e281]:
                    - generic [ref=f3e282]:
                      - generic [ref=f3e283]: Rainy Day
                      - generic [ref=f3e284]: 20 orders pushed to tomorrow with bulky materials and delivery.
                    - button "Generate Day" [ref=f3e285] [cursor=pointer]
                  - generic [ref=f3e286]:
                    - generic [ref=f3e287]:
                      - term [ref=f3e288]: Customers
                      - definition [ref=f3e289]: "10"
                    - generic [ref=f3e290]:
                      - term [ref=f3e291]: Orders
                      - definition [ref=f3e292]: "20"
                    - generic [ref=f3e293]:
                      - term [ref=f3e294]: WhatsApp
                      - definition [ref=f3e295]: "12"
                    - generic [ref=f3e296]:
                      - term [ref=f3e297]: Telegram
                      - definition [ref=f3e298]: "8"
                - generic [ref=f3e299]:
                  - generic [ref=f3e300]:
                    - generic [ref=f3e301]:
                      - generic [ref=f3e302]: Construction Company
                      - generic [ref=f3e303]: 10 customers placing high-priority delivery orders for heavy materials.
                    - button "Generate Day" [ref=f3e304] [cursor=pointer]
                  - generic [ref=f3e305]:
                    - generic [ref=f3e306]:
                      - term [ref=f3e307]: Customers
                      - definition [ref=f3e308]: "10"
                    - generic [ref=f3e309]:
                      - term [ref=f3e310]: Orders
                      - definition [ref=f3e311]: "12"
                    - generic [ref=f3e312]:
                      - term [ref=f3e313]: WhatsApp
                      - definition [ref=f3e314]: "8"
                    - generic [ref=f3e315]:
                      - term [ref=f3e316]: Telegram
                      - definition [ref=f3e317]: "4"
                - generic [ref=f3e318]:
                  - generic [ref=f3e319]:
                    - generic [ref=f3e320]:
                      - generic [ref=f3e321]: Farmers Market
                      - generic [ref=f3e322]: 20 pickup orders for today with cash and SINPE payments.
                    - button "Generate Day" [ref=f3e323] [cursor=pointer]
                  - generic [ref=f3e324]:
                    - generic [ref=f3e325]:
                      - term [ref=f3e326]: Customers
                      - definition [ref=f3e327]: "12"
                    - generic [ref=f3e328]:
                      - term [ref=f3e329]: Orders
                      - definition [ref=f3e330]: "20"
                    - generic [ref=f3e331]:
                      - term [ref=f3e332]: WhatsApp
                      - definition [ref=f3e333]: "14"
                    - generic [ref=f3e334]:
                      - term [ref=f3e335]: Telegram
                      - definition [ref=f3e336]: "6"
            - generic [ref=f3e337]:
              - generic [ref=f3e338]:
                - generic [ref=f3e339]:
                  - generic [ref=f3e340]:
                    - heading "Create Custom Customer Message" [level=2] [ref=f3e341]
                    - paragraph [ref=f3e342]: Inject a single customer message through the same ingestion pipeline used in production.
                  - generic [ref=f3e343]: "Current provider: WhatsApp"
                - generic [ref=f3e344]:
                  - generic [ref=f3e345]:
                    - generic [ref=f3e346]:
                      - text: Customer
                      - combobox "Customer" [ref=f3e347]:
                        - option "New customer" [selected]
                        - option "Existing customer"
                    - generic [ref=f3e348]:
                      - text: Provider
                      - combobox "Provider" [ref=f3e349]:
                        - option "WhatsApp" [selected]
                        - option "Telegram"
                        - option "Instagram placeholder"
                  - generic [ref=f3e350]:
                    - generic [ref=f3e351]:
                      - text: Customer name
                      - textbox "Customer name" [ref=f3e352]: Maria Lopez
                    - generic [ref=f3e353]:
                      - text: Customer phone / chat id
                      - textbox "Customer phone / chat id" [ref=f3e354]: "50255510001"
                  - generic [ref=f3e355]:
                    - text: Message
                    - textbox "Message" [ref=f3e356]:
                      - /placeholder: Ocupo 20 bloques para manana temprano. Yo paso por ellos. Pago por SINPE.
                  - button "Inject Message" [ref=f3e357] [cursor=pointer]
              - generic [ref=f3e358]:
                - generic [ref=f3e359]:
                  - heading "Random Message Generator" [level=2] [ref=f3e360]
                  - paragraph [ref=f3e361]: Generate mixed realistic messages for products, delivery, pickup, dates, payments and urgency.
                  - generic [ref=f3e363]:
                    - button "Generate 10 Messages" [ref=f3e364] [cursor=pointer]
                    - button "Generate 25 Messages" [ref=f3e365] [cursor=pointer]
                    - button "Generate 50 Messages" [ref=f3e366] [cursor=pointer]
                    - button "Generate 100 Messages" [ref=f3e367] [cursor=pointer]
                - generic [ref=f3e368]:
                  - heading "Simulate Business Day" [level=2] [ref=f3e369]
                  - paragraph [ref=f3e370]: Replay a timed sequence that naturally updates the live queue and operations agenda.
                  - generic [ref=f3e372]:
                    - button "1x" [ref=f3e373] [cursor=pointer]
                    - button "5x" [ref=f3e374] [cursor=pointer]
                    - button "10x" [ref=f3e375] [cursor=pointer]
            - generic [ref=f3e376]:
              - heading "Reset" [level=2] [ref=f3e377]
              - paragraph [ref=f3e378]: Clear only generated simulation data. Real customers and real operations data stay untouched.
              - generic [ref=f3e379]:
                - button "Reset Today simulation" [ref=f3e381] [cursor=pointer]
                - button "Reset Demo customers" [ref=f3e383] [cursor=pointer]
                - button "Reset Demo orders" [ref=f3e385] [cursor=pointer]
                - button "Reset Messages" [ref=f3e387] [cursor=pointer]
                - button "Reset Notifications" [ref=f3e389] [cursor=pointer]
                - button "Reset Webhook logs" [ref=f3e391] [cursor=pointer]
            - generic [ref=f3e392]:
              - generic [ref=f3e393]:
                - generic [ref=f3e394]:
                  - heading "Legacy Scenario Generator" [level=2] [ref=f3e395]
                  - paragraph [ref=f3e396]: Predefined cards populate the app with realistic operational data in one click.
                - generic [ref=f3e397]:
                  - generic [ref=f3e398]: Orders
                  - generic [ref=f3e399]: Customers
                  - generic [ref=f3e400]: Channels / VIP / Duplicates
              - generic [ref=f3e401]:
                - generic [ref=f3e402]:
                  - generic [ref=f3e404]:
                    - generic [ref=f3e405]: Ferretería pequeña
                    - generic [ref=f3e406]: 20-40s
                  - generic [ref=f3e407]:
                    - generic [ref=f3e408]:
                      - term [ref=f3e409]: Customers
                      - definition [ref=f3e410]: "15"
                    - generic [ref=f3e411]:
                      - term [ref=f3e412]: Orders
                      - definition [ref=f3e413]: "30"
                    - generic [ref=f3e414]:
                      - term [ref=f3e415]: VIP
                      - definition [ref=f3e416]: "3"
                    - generic [ref=f3e417]:
                      - term [ref=f3e418]: Duplicates
                      - definition [ref=f3e419]: "2"
                  - generic [ref=f3e420]:
                    - generic [ref=f3e421]: Channels
                    - generic [ref=f3e422]: Whatsapp 20 / Telegram 10
                  - paragraph [ref=f3e423]: Este escenario crea datos demo marcados para pruebas y no toca datos reales.
                  - generic [ref=f3e424]:
                    - button "Generar escenario" [ref=f3e425] [cursor=pointer]
                    - button "Reiniciar datos demo" [ref=f3e426] [cursor=pointer]
                - generic [ref=f3e427]:
                  - generic [ref=f3e428]:
                    - generic [ref=f3e429]:
                      - generic [ref=f3e430]: Ferreteria grande
                      - generic [ref=f3e431]: 2-5m
                    - button "Generate" [ref=f3e432] [cursor=pointer]
                  - generic [ref=f3e433]:
                    - generic [ref=f3e434]:
                      - term [ref=f3e435]: Customers
                      - definition [ref=f3e436]: "60"
                    - generic [ref=f3e437]:
                      - term [ref=f3e438]: Orders
                      - definition [ref=f3e439]: "250"
                    - generic [ref=f3e440]:
                      - term [ref=f3e441]: VIP
                      - definition [ref=f3e442]: "15"
                    - generic [ref=f3e443]:
                      - term [ref=f3e444]: Duplicates
                      - definition [ref=f3e445]: "20"
                  - generic [ref=f3e446]:
                    - generic [ref=f3e447]: Channels
                    - generic [ref=f3e448]: Whatsapp 160 / Telegram 90
                - generic [ref=f3e449]:
                  - generic [ref=f3e450]:
                    - generic [ref=f3e451]:
                      - generic [ref=f3e452]: Tramo de feria
                      - generic [ref=f3e453]: 30-60s
                    - button "Generate" [ref=f3e454] [cursor=pointer]
                  - generic [ref=f3e455]:
                    - generic [ref=f3e456]:
                      - term [ref=f3e457]: Customers
                      - definition [ref=f3e458]: "20"
                    - generic [ref=f3e459]:
                      - term [ref=f3e460]: Orders
                      - definition [ref=f3e461]: "50"
                    - generic [ref=f3e462]:
                      - term [ref=f3e463]: VIP
                      - definition [ref=f3e464]: "4"
                    - generic [ref=f3e465]:
                      - term [ref=f3e466]: Duplicates
                      - definition [ref=f3e467]: "4"
                  - generic [ref=f3e468]:
                    - generic [ref=f3e469]: Channels
                    - generic [ref=f3e470]: Whatsapp 30 / Telegram 20
                - generic [ref=f3e471]:
                  - generic [ref=f3e472]:
                    - generic [ref=f3e473]:
                      - generic [ref=f3e474]: Mini supermercado
                      - generic [ref=f3e475]: 40-80s
                    - button "Generate" [ref=f3e476] [cursor=pointer]
                  - generic [ref=f3e477]:
                    - generic [ref=f3e478]:
                      - term [ref=f3e479]: Customers
                      - definition [ref=f3e480]: "25"
                    - generic [ref=f3e481]:
                      - term [ref=f3e482]: Orders
                      - definition [ref=f3e483]: "60"
                    - generic [ref=f3e484]:
                      - term [ref=f3e485]: VIP
                      - definition [ref=f3e486]: "5"
                    - generic [ref=f3e487]:
                      - term [ref=f3e488]: Duplicates
                      - definition [ref=f3e489]: "5"
                  - generic [ref=f3e490]:
                    - generic [ref=f3e491]: Channels
                    - generic [ref=f3e492]: Whatsapp 40 / Telegram 20
                - generic [ref=f3e493]:
                  - generic [ref=f3e494]:
                    - generic [ref=f3e495]:
                      - generic [ref=f3e496]: Distribuidora
                      - generic [ref=f3e497]: 1-2m
                    - button "Generate" [ref=f3e498] [cursor=pointer]
                  - generic [ref=f3e499]:
                    - generic [ref=f3e500]:
                      - term [ref=f3e501]: Customers
                      - definition [ref=f3e502]: "40"
                    - generic [ref=f3e503]:
                      - term [ref=f3e504]: Orders
                      - definition [ref=f3e505]: "100"
                    - generic [ref=f3e506]:
                      - term [ref=f3e507]: VIP
                      - definition [ref=f3e508]: "8"
                    - generic [ref=f3e509]:
                      - term [ref=f3e510]: Duplicates
                      - definition [ref=f3e511]: "10"
                  - generic [ref=f3e512]:
                    - generic [ref=f3e513]: Channels
                    - generic [ref=f3e514]: Whatsapp 70 / Telegram 30
                - generic [ref=f3e515]:
                  - generic [ref=f3e516]:
                    - generic [ref=f3e517]:
                      - generic [ref=f3e518]: Restaurant
                      - generic [ref=f3e519]: 25-50s
                    - button "Generate" [ref=f3e520] [cursor=pointer]
                  - generic [ref=f3e521]:
                    - generic [ref=f3e522]:
                      - term [ref=f3e523]: Customers
                      - definition [ref=f3e524]: "18"
                    - generic [ref=f3e525]:
                      - term [ref=f3e526]: Orders
                      - definition [ref=f3e527]: "45"
                    - generic [ref=f3e528]:
                      - term [ref=f3e529]: VIP
                      - definition [ref=f3e530]: "4"
                    - generic [ref=f3e531]:
                      - term [ref=f3e532]: Duplicates
                      - definition [ref=f3e533]: "3"
                  - generic [ref=f3e534]:
                    - generic [ref=f3e535]: Channels
                    - generic [ref=f3e536]: Whatsapp 30 / Telegram 15
            - generic [ref=f3e537]:
              - generic [ref=f3e538]:
                - generic [ref=f3e539]:
                  - heading "Legacy Quick Generators" [level=2] [ref=f3e540]
                  - paragraph [ref=f3e541]: Generate a small, medium or large wave of orders using the currently selected provider.
                - generic [ref=f3e542]: "Current provider: WhatsApp"
              - generic [ref=f3e544]:
                - button "Generate 1 order" [ref=f3e545] [cursor=pointer]
                - button "Generate 5 orders" [ref=f3e546] [cursor=pointer]
                - button "Generate 20 orders" [ref=f3e547] [cursor=pointer]
                - button "Generate 50 orders" [ref=f3e548] [cursor=pointer]
                - button "Generate 100 orders" [ref=f3e549] [cursor=pointer]
            - generic [ref=f3e550]:
              - generic [ref=f3e551]:
                - heading "Legacy Customer Generator" [level=2] [ref=f3e552]
                - paragraph [ref=f3e553]: Generate customers only using random Costa Rican names and phone numbers.
                - generic [ref=f3e555]:
                  - button "Generate 10 customers" [ref=f3e556] [cursor=pointer]
                  - button "Generate 50 customers" [ref=f3e557] [cursor=pointer]
                  - button "Generate 100 customers" [ref=f3e558] [cursor=pointer]
              - generic [ref=f3e559]:
                - heading "Legacy Operational QA" [level=2] [ref=f3e560]
                - paragraph [ref=f3e561]: Fast buttons for duplicate, VIP, parser failure, unknown product, busy day and empty inbox checks.
                - generic [ref=f3e562]:
                  - button "Create duplicate scenario Repeated demand from the same customer to trigger duplicate detection." [ref=f3e564] [cursor=pointer]:
                    - text: Create duplicate scenario
                    - generic [ref=f3e565]: Repeated demand from the same customer to trigger duplicate detection.
                  - button "Create VIP scenario Many orders from one customer to classify it as VIP." [ref=f3e567] [cursor=pointer]:
                    - text: Create VIP scenario
                    - generic [ref=f3e568]: Many orders from one customer to classify it as VIP.
                  - button "Create parser failures Malformed or low-confidence payloads for parser QA." [ref=f3e570] [cursor=pointer]:
                    - text: Create parser failures
                    - generic [ref=f3e571]: Malformed or low-confidence payloads for parser QA.
                  - button "Create unknown products Products that should miss catalog matching." [ref=f3e573] [cursor=pointer]:
                    - text: Create unknown products
                    - generic [ref=f3e574]: Products that should miss catalog matching.
                  - button "Create busy day High-volume mixed provider traffic with natural timing." [ref=f3e576] [cursor=pointer]:
                    - text: Create busy day
                    - generic [ref=f3e577]: High-volume mixed provider traffic with natural timing.
                  - button "Create empty inbox Clears demo inbox data for a blank operations view." [ref=f3e579] [cursor=pointer]:
                    - text: Create empty inbox
                    - generic [ref=f3e580]: Clears demo inbox data for a blank operations view.
            - generic [ref=f3e581]:
              - heading "Legacy Demo Reset" [level=2] [ref=f3e582]
              - paragraph [ref=f3e583]: Deletes demo orders or customers only. Admin users and organizations remain untouched.
              - generic [ref=f3e584]:
                - button "Delete generated demo orders" [ref=f3e586] [cursor=pointer]
                - button "Delete generated demo customers" [ref=f3e588] [cursor=pointer]
                - button "Reset demo environment" [ref=f3e590] [cursor=pointer]
          - complementary [ref=f3e591]:
            - generic [ref=f3e592]:
              - heading "Metrics" [level=2] [ref=f3e593]
              - generic [ref=f3e594]:
                - generic [ref=f3e595]:
                  - generic [ref=f3e596]: Generated customers
                  - generic [ref=f3e597]: "1"
                - generic [ref=f3e598]:
                  - generic [ref=f3e599]: Generated orders
                  - generic [ref=f3e600]: "1"
                - generic [ref=f3e601]:
                  - generic [ref=f3e602]: WhatsApp
                  - generic [ref=f3e603]: "1"
                - generic [ref=f3e604]:
                  - generic [ref=f3e605]: Telegram
                  - generic [ref=f3e606]: "0"
                - generic [ref=f3e607]:
                  - generic [ref=f3e608]: VIP
                  - generic [ref=f3e609]: "0"
                - generic [ref=f3e610]:
                  - generic [ref=f3e611]: Duplicates
                  - generic [ref=f3e612]: "0"
            - generic [ref=f3e613]:
              - heading "Business Metrics" [level=2] [ref=f3e614]
              - generic [ref=f3e615]:
                - generic [ref=f3e616]:
                  - generic [ref=f3e617]: Customers
                  - generic [ref=f3e618]: "29"
                - generic [ref=f3e619]:
                  - generic [ref=f3e620]: Orders
                  - generic [ref=f3e621]: "188"
                - generic [ref=f3e622]:
                  - generic [ref=f3e623]: Delivery
                  - generic [ref=f3e624]: "143"
                - generic [ref=f3e625]:
                  - generic [ref=f3e626]: Pickup
                  - generic [ref=f3e627]: "45"
                - generic [ref=f3e628]:
                  - generic [ref=f3e629]: Today
                  - generic [ref=f3e630]: "34"
                - generic [ref=f3e631]:
                  - generic [ref=f3e632]: Tomorrow
                  - generic [ref=f3e633]: "15"
                - generic [ref=f3e634]:
                  - generic [ref=f3e635]: Urgent
                  - generic [ref=f3e636]: "116"
                - generic [ref=f3e637]:
                  - generic [ref=f3e638]: VIP
                  - generic [ref=f3e639]: "0"
                - generic [ref=f3e640]:
                  - generic [ref=f3e641]: Duplicates
                  - generic [ref=f3e642]: "119"
                - generic [ref=f3e643]:
                  - generic [ref=f3e644]: Avg parser confidence
                  - generic [ref=f3e645]: "0.74"
                - generic [ref=f3e646]:
                  - generic [ref=f3e647]: Avg priority score
                  - generic [ref=f3e648]: "72.98"
                - generic [ref=f3e649]:
                  - generic [ref=f3e650]: Avg SLA
                  - generic [ref=f3e651]: "263.73"
            - generic [ref=f3e652]:
              - heading "Provider Details" [level=2] [ref=f3e653]
              - generic [ref=f3e654]:
                - generic [ref=f3e655]: Real Meta Cloud webhook shape
                - generic [ref=f3e656]: Supports status and non-text payloads
            - generic [ref=f3e657]:
              - heading "Connection" [level=2] [ref=f3e658]
              - generic [ref=f3e659]:
                - generic [ref=f3e660]:
                  - term [ref=f3e661]: Status
                  - definition [ref=f3e662]: draft
                - generic [ref=f3e663]:
                  - term [ref=f3e664]: Provider status
                  - definition [ref=f3e665]: draft
                - generic [ref=f3e666]:
                  - term [ref=f3e667]: Organization
                  - definition [ref=f3e668]: "1"
                - generic [ref=f3e669]:
                  - term [ref=f3e670]: Channel
                  - definition [ref=f3e671]: whatsapp
```

# Test source

```ts
  63  |     await page.goto('/login');
  64  |     await page.screenshot({ path: path.resolve(screenshotDir, '01-login.png'), fullPage: true });
  65  | 
  66  |     await page.locator('#email').fill('owner@local.test');
  67  |     await page.locator('#password').fill('password');
  68  |     await page.getByRole('button', { name: 'Entrar' }).click();
  69  |     await expect(page).toHaveURL(/\/dashboard/);
  70  | 
  71  |     await page.goto('/developer/webhook-simulator');
  72  |     await expect(page.getByText('Business Scenario Simulator')).toBeVisible();
  73  | 
  74  |     const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  75  |     if (!csrfToken) {
  76  |         throw new Error('Missing CSRF token on developer toolkit page.');
  77  |     }
  78  | 
  79  |     const customMessageSection = page.locator('section').filter({ hasText: 'Create Custom Customer Message' });
  80  |     await customMessageSection.locator('select[name="customer_mode"]').selectOption('new');
  81  |     await customMessageSection.locator('select[name="provider"]').selectOption('whatsapp');
  82  |     await customMessageSection.locator('input[name="customer_name"]').fill('Maria Lopez');
  83  |     await customMessageSection.locator('input[name="customer_phone"]').fill('50255571234');
  84  |     await customMessageSection.locator('textarea[name="message"]').fill('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE');
  85  |     await page.getByRole('button', { name: 'Inject Message' }).click();
  86  |     await expect(page.getByText('Custom message injected through the production pipeline.')).toBeVisible();
  87  |     await expect(page).toHaveURL(/\/developer\/webhook-simulator\/generate/);
  88  |     await page.screenshot({ path: path.resolve(screenshotDir, '02-developer-toolkit.png'), fullPage: true });
  89  | 
  90  |     await postBusinessMessage(page, csrfToken, {
  91  |         provider: 'telegram',
  92  |         customer_name: 'Ana Ruiz',
  93  |         customer_phone: '50255573333',
  94  |         message: '3 pinturas para manana temprano, yo paso por ellas y pago en efectivo',
  95  |     });
  96  | 
  97  |     await postBusinessMessage(page, csrfToken, {
  98  |         provider: 'telegram',
  99  |         customer_name: 'Maria Lopez',
  100 |         customer_phone: '50255571234',
  101 |         message: 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
  102 |     });
  103 | 
  104 |     const feed = await fetchOperationsFeed(page);
  105 |     const firstOrder = findOrder(feed.inbox, {
  106 |         customerName: 'Maria Lopez',
  107 |         provider: 'whatsapp',
  108 |         text: '20 bloques',
  109 |     });
  110 | 
  111 |     const secondOrder = findOrder(feed.inbox, {
  112 |         customerName: 'Ana Ruiz',
  113 |         provider: 'telegram',
  114 |         text: 'pinturas',
  115 |     });
  116 | 
  117 |     if (!firstOrder) {
  118 |         throw new Error('Could not find the primary WhatsApp order in the operations feed.');
  119 |     }
  120 | 
  121 |     if (!secondOrder) {
  122 |         throw new Error('Could not find the Telegram pickup order in the operations feed.');
  123 |     }
  124 | 
  125 |     const operationsPage = await page.context().newPage();
  126 |     operationsPage.on('console', (message) => {
  127 |         if (message.type() === 'error') {
  128 |             consoleErrors.push(message.text());
  129 |         }
  130 |     });
  131 |     operationsPage.on('pageerror', (error) => {
  132 |         pageErrors.push(error.message);
  133 |     });
  134 |     operationsPage.on('response', (response) => {
  135 |         if (response.status() >= 500) {
  136 |             http500Responses.push({ url: response.url(), status: response.status() });
  137 |         }
  138 |     });
  139 |     operationsPage.on('requestfailed', (request) => {
  140 |         const failure = request.failure()?.errorText ?? 'request failed';
  141 |         if (/ERR_ABORTED|canceled/i.test(failure)) {
  142 |             return;
  143 |         }
  144 | 
  145 |         failedRequests.push({
  146 |             url: request.url(),
  147 |             failure,
  148 |         });
  149 |     });
  150 | 
  151 |     await operationsPage.goto('/operations', { waitUntil: 'domcontentloaded' });
  152 |     await expect(operationsPage.getByText('DO NOW', { exact: true })).toBeVisible();
  153 |     await expect(operationsPage.getByText('NEXT', { exact: true })).toBeVisible();
  154 |     await expect(operationsPage.getByText('COMPLETED', { exact: true })).toBeVisible();
  155 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '03-operations-home.png'), fullPage: true });
  156 | 
  157 |     const doNowSection = operationsPage.locator('section').filter({ hasText: 'DO NOW' });
  158 |     const doNowCard = doNowSection.locator('article').filter({ hasText: 'Maria Lopez' }).first();
  159 |     await expect(doNowCard).toBeVisible();
  160 |     await doNowCard.screenshot({ path: path.resolve(screenshotDir, '04-do-now-order.png') });
  161 | 
  162 |     await Promise.all([
> 163 |         operationsPage.waitForResponse((response) => response.url().includes(`/operations/orders/${firstOrder.id}/snapshot`) && response.status() === 200),
      |                        ^ Error: page.waitForResponse: Test timeout of 120000ms exceeded.
  164 |         doNowCard.click(),
  165 |     ]);
  166 | 
  167 |     const drawer = operationsPage.locator('aside').filter({ hasText: 'Detail drawer' });
  168 |     await expect(drawer).toBeVisible();
  169 |     await expect(drawer.getByText('Items', { exact: true })).toBeVisible();
  170 |     await expect(drawer).toContainText('Prepare');
  171 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '05-order-drawer.png'), fullPage: true });
  172 | 
  173 |     await transitionOrder(operationsPage, drawer, 'Prepare', 'Prepare', 'Confirmado');
  174 | 
  175 |     await transitionOrder(operationsPage, drawer, 'Prepare', 'Ready', 'Preparando');
  176 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '06-order-preparing.png'), fullPage: true });
  177 | 
  178 |     await transitionOrder(operationsPage, drawer, 'Ready', 'Dispatch', 'Listo');
  179 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '07-order-ready.png'), fullPage: true });
  180 | 
  181 |     await transitionOrder(operationsPage, drawer, 'Dispatch', null, 'Despachado');
  182 |     await expect(drawer).toContainText('Complete');
  183 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '08-order-dispatched.png'), fullPage: true });
  184 | 
  185 |     await drawer.getByRole('button', { name: 'Close' }).click();
  186 |     await operationsPage.reload();
  187 |     await expect(operationsPage.getByText('DO NOW', { exact: true })).toBeVisible();
  188 |     await operationsPage.locator('details').filter({ hasText: 'COMPLETED' }).locator('summary').click();
  189 |     await expect(operationsPage.locator('details').filter({ hasText: 'COMPLETED' })).toContainText('Maria Lopez');
  190 |     await operationsPage.screenshot({ path: path.resolve(screenshotDir, '09-completed-after-refresh.png'), fullPage: true });
  191 | 
  192 |     const customersPage = await page.context().newPage();
  193 |     customersPage.on('console', (message) => {
  194 |         if (message.type() === 'error') {
  195 |             consoleErrors.push(message.text());
  196 |         }
  197 |     });
  198 |     customersPage.on('pageerror', (error) => {
  199 |         pageErrors.push(error.message);
  200 |     });
  201 |     customersPage.on('response', (response) => {
  202 |         if (response.status() >= 500) {
  203 |             http500Responses.push({ url: response.url(), status: response.status() });
  204 |         }
  205 |     });
  206 |     customersPage.on('requestfailed', (request) => {
  207 |         const failure = request.failure()?.errorText ?? 'request failed';
  208 |         if (/ERR_ABORTED|canceled/i.test(failure)) {
  209 |             return;
  210 |         }
  211 | 
  212 |         failedRequests.push({
  213 |             url: request.url(),
  214 |             failure,
  215 |         });
  216 |     });
  217 | 
  218 |     await customersPage.goto('/customers', { waitUntil: 'domcontentloaded' });
  219 |     await expect(customersPage.getByRole('heading', { name: 'Clientes', exact: true })).toBeVisible();
  220 |     const customerCard = customersPage.locator('article').filter({ hasText: 'Maria Lopez' }).first();
  221 |     await expect(customerCard).toBeVisible();
  222 |     await customerCard.getByRole('link', { name: 'Ver cliente' }).click();
  223 |     await expect(customersPage.getByRole('heading', { name: 'Cliente', exact: true })).toBeVisible();
  224 |     await customersPage.screenshot({ path: path.resolve(screenshotDir, '10-customer-detail.png'), fullPage: true });
  225 | 
  226 |     const summary = {
  227 |         browser: browserName,
  228 |         console_errors: consoleErrors,
  229 |         page_errors: pageErrors,
  230 |         http_500_responses: http500Responses,
  231 |         failed_requests: failedRequests,
  232 |         warnings,
  233 |         failures: [],
  234 |         screenshots: screenshotPaths,
  235 |         backend_status_dispatched: true,
  236 |         workflow_completed: true,
  237 |         metrics: {
  238 |             whatsapp_tested: true,
  239 |             telegram_tested: true,
  240 |             login_visible: true,
  241 |             developer_toolkit_visible: true,
  242 |             operations_home_visible: true,
  243 |             operations_drawer_visible: true,
  244 |             operations_transitions_completed: true,
  245 |             completed_after_refresh_visible: true,
  246 |             customer_detail_visible: true,
  247 |             completed_ui_inconsistent: false,
  248 |         },
  249 |     };
  250 | 
  251 |     await fs.writeFile(summaryPath, JSON.stringify(summary, null, 2) + '\n');
  252 | 
  253 |     if (consoleErrors.length > 0 || pageErrors.length > 0 || http500Responses.length > 0 || failedRequests.length > 0) {
  254 |         throw new Error(`Pilot documentation browser run recorded errors: ${JSON.stringify({
  255 |             consoleErrors: consoleErrors.length,
  256 |             pageErrors: pageErrors.length,
  257 |             http500Responses: http500Responses.length,
  258 |             failedRequests: failedRequests.length,
  259 |         })}`);
  260 |     }
  261 | });
  262 | 
  263 | async function postBusinessMessage(page, csrfToken, payload) {
```