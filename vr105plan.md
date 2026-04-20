# VR 10.5 Teami Töö: Projekti Logide Integreerimine LogForwardi API-ga

## Eesmärk

Luua meie projektile automaatne logiedastuse lahendus, mis saadab Apache ja vajadusel ka MySQL logid LogForwardi API-sse.

Meie projekti puhul on see realistlik teha failipõhise logide jälgimisega, sest logid juba tekivad hosti `logs/` kausta ning repo-s on olemas eraldi shipper-skriptide muster.

## Praegune olukord projektis

### Kus rakendus jookseb

- Rakendus töötab Docker Compose keskkonnas.
- Veebirakendus jookseb `webserver` teenuses Apache + PHP peal.
- Andmebaas jookseb `db` teenuses MySQL 8.0 peal.

### Kuhu logid kirjutatakse

- Apache access log: `logs/apache_access.log`
- Apache error log: `logs/apache_error.log`
- MySQL general log: `logs/mysql.log`

Need failid on seotud konteineritest hosti `logs/` kausta läbi `docker-compose.yml` volume-mountide.

### Mis on juba olemas

- `webserver/apache.conf` suunab Apache logid failidesse `/var/log/apache2/access.log` ja `/var/log/apache2/error.log`.
- `docker-compose.yml` mapib need failid hosti `./logs/` kausta.
- Repo-s on olemas logi-shipperid:
  - `scripts/send_apache_logs.py`
  - `scripts/send_mysql_logs.py`
  - `scripts/log_shipper_common.py`

Oluline tähelepanek: praegused shipperid saadavad logid Elasticsearchi, mitte LogForwardi API-sse. Seega VR 10.5 jaoks tuleb teha eraldi Forward API lahendus või olemasolev shipperi loogika ümber kasutada.

## Valitud strateegia

### Otsus

Valime **Variant A: eraldi Pythoni skript, mis loeb logifaile ja saadab read API-sse**.

### Miks see on meie projektile parim

- See sobib kokku olemasoleva arhitektuuriga, kus logid kirjutatakse juba failidesse.
- Me ei pea muutma rakenduse PHP koodi iga logisündmuse juures.
- Sama lahendust saab kasutada nii Apache kui ka MySQL logide jaoks.
- See on demoks lihtsam: üks protsess kirjutab logi, teine protsess loeb ja saadab.

### Miks mitte rakendusesisene saatmine

Rakendusesisene saatmine oleks mõistlik ainult siis, kui sooviksime saata väga kindlaid äriloogika sündmusi otse PHP koodist. VR 10.5 jaoks on failipõhine jälgimine parem, sest ülesanne keskendub just olemasolevate logifailide integreerimisele.

## LogForwardi API faktid

Kontrollitud dokumentatsioon:

- Swagger UI: `https://srv1073565.hstgr.cloud:8443/docs`
- Swagger JSON: `https://srv1073565.hstgr.cloud:8443/api/v1/swagger.json`

Olulised endpointid:

- Test ühendusele: `GET /api/v1/ping` (ei vaja Swaggeri järgi autentimist)
- Tervisekontroll: `GET /api/v1/health` (vajab autentimist)
- Logide saatmine: `POST /api/v1/logs`

Oluline detail: ainult base URL `https://srv1073565.hstgr.cloud:8443/api/v1` ei ole saatmise endpoint. Logide edastamiseks tuleb kasutada teed `/api/v1/logs`.

Swaggeri järgi on `POST /api/v1/logs` request body **JSON massiiv** logikirjetest.

Ühe kirje väljad:

- kohustuslikud:
  - `level`
  - `message`
- soovituslikud:
  - `service`
  - `timestamp`
  - `metadata`

Ülesande tekstist lähtuvalt tuleb autentimiseks kasutada päiseid:

- `x-api-id`
- `x-api-key`

### Kasutatavad API andmed

Praeguse tiimi konfiguratsiooni järgi kasutame järgmisi väärtusi:

```env
API_ID=lidlempire
API_PUBLIC_KEY=Lidl123Emp
API_PUBLIC_INDEX=lidlempire_1
```

Nende väljade kasutus meie plaanis:

- `API_ID` -> väärtus, mis läheb päisesse `x-api-id`
- `API_PUBLIC_KEY` -> väärtus, mis läheb päisesse `x-api-key`
- `API_PUBLIC_INDEX` -> tiimile antud API indeks, mida hoiame konfiguratsioonis edasiseks kasutuseks

## Teostusplaan

### Samm 1: Kokku leppida logiallikas ja teenuse nimi

Esimeses versioonis saadame ainult Apache logisid, sest need näitavad kohe kasutaja päringuid ja serveri vigu.

Kokkulepe:

- `service`: `kawaiiemoji-web`
- esmane testfail: `logs/apache_access.log`
- teine fail pärast esimest edu: `logs/apache_error.log`
- lisafaas: `logs/mysql.log`

### Samm 2: Lihtne algus skriptiga "viimased 5 rida"

Loome uue skripti näiteks nimega `scripts/send_forward_last5.py`.

Skripti eesmärk:

1. Avada määratud logifail.
2. Lugeda sisse viimased 5 rida.
3. Käia read ükshaaval läbi.
4. Saata iga rida eraldi `POST /api/v1/logs` päringuga.
5. Kuvada terminalis vastuse staatuskood ja vajadusel veateade.

Soovitatav request body ühe rea kohta:

```json
[
  {
    "level": "INFO",
    "message": "GET /index.php 200",
    "service": "kawaiiemoji-web",
    "timestamp": "2026-04-20T10:00:00Z",
    "metadata": {
      "source_file": "apache_access.log",
      "project": "kawaiiemoji",
      "environment": "docker"
    }
  }
]
```

Märkus: `metadata` võib olla alguses minimaalne, aga ta peab olemas olema vähemalt tühja objektina `{}`.

### Samm 3: Teha lihtne tasemete kaardistus

Kuna faili read ei sisalda alati kohe standardset taset, kasutame alguses lihtsat reeglit:

- access log read -> `INFO`
- Apache 4xx -> `WARN`
- Apache 5xx -> `ERROR`
- error log read -> `ERROR` või Apache rea taseme põhjal
- MySQL üldlogi -> enamasti `INFO`

### Samm 4: Reaalajas jälgimise skript

Kui "viimased 5 rida" töötab, loome teise skripti näiteks `scripts/send_forward_tail.py`.

Selle tööloogika:

1. Ava fail lugemiseks.
2. Liigu faili lõppu `seek(0, 2)`.
3. Jää lõpmatus tsüklis ootama.
4. Kui tekib uus rida, loe see sisse.
5. Muuda rida JSON kujule.
6. Saada see kohe LogForwardi API-sse.

See on sisuliselt Pythonis `tail -f` lahendus.

### Samm 5: Töökindluse lisamine

Reaalajas skript peaks sisaldama vähemalt järgmisi kaitseid:

- API URL ja võtmed keskkonnamuutujates, mitte hardcode'ituna
- timeout HTTP päringutele
- veakäsitlus `try/except`
- lühike retry või paus ebaõnnestumise järel
- logifaili offseti salvestamine state-faili, kui soovime hiljem kadudeta jätkamist

Soovitatavad keskkonnamuutujad:

- `FORWARD_API_BASE_URL`
- `FORWARD_API_ID`
- `FORWARD_API_KEY`
- `FORWARD_SERVICE_NAME`
- `FORWARD_LOG_FILES`
- `FORWARD_STATE_FILE`
- `FORWARD_POLL_INTERVAL`

Praktiline kokkulepe:

- päris võtmeid hoida lokaalses `.env` failis või shelli keskkonnamuutujates
- repo-sse commitida ainult `.env.example`
- võtmeid mitte kirjutada `vr105plan.md` või teistesse jagatavatesse dokumentidesse

### Samm 6: Docker Compose integreerimine

Kui skript käsitsi töötab, lisame eraldi teenuse näiteks:

- `forward-log-shipper`

Teenuse ülesanne:

- mountida `./scripts`
- mountida `./logs`
- käivitada reaalajas shipper-skript
- hoida state-faili eraldi volume'is

Selle tulemusel hakkab logide saatmine tööle automaatselt koos projektiga.

## Täpne tööjaotus meeskonnas

### Maksim - infrastruktuur ja logiallikad

- kontrollib üle, et `docker-compose.yml` ja `webserver/apache.conf` kirjutavad logid õigesse kohta
- kinnitab, et põhiline testfail on `logs/apache_access.log` ja teine fail on `logs/apache_error.log`
- teeb valmis esimese töötava prooviversiooni skriptist `scripts/send_forward_last5.py`
- lisab hiljem `docker-compose.yml` faili eraldi teenuse `forward-log-shipper`
- kontrollib, et pärast brauseripäringut tekib uus rida `apache_access.log` faili

Valmis tulemus Maksimilt:

- logifailid tekivad õigesse kohta
- "viimased 5 rida" skript käivitub
- Docker Compose teenuse kirjeldus on olemas

### Nikita - payload, parsingu loogika ja API saatmine

- paneb paika, milline näeb välja iga LogForwardi API-sse saadetav JSON kirje
- määrab kindlaks väljad `level`, `message`, `service`, `timestamp` ja `metadata`
- teeb loogika, mis muudab Apache access log rea API jaoks sobivaks payloadiks
- lisab tasemete kaardistuse:
  - `2xx/3xx` -> `INFO`
  - `4xx` -> `WARN`
  - `5xx` -> `ERROR`
- valmistab ette teise etapi jaoks ka Apache error log ja vajadusel MySQL logi teisendamise

Valmis tulemus Nikitalt:

- korrektne JSON näidispayload
- töötav rea -> JSON teisendus
- testitud `POST /api/v1/logs` päringuloogika

### Hussein - koordineerimine, võtmed, testimine ja dokumenteerimine

- kogub kokku õiged API autentimisandmed: `x-api-id` ja `x-api-key`
- kontrollib enne demo tegemist, et `GET /api/v1/ping` ja `GET /api/v1/health` töötavad
- testib koos meeskonnaga läbi kaks stsenaariumi:
  - viimaste 5 rea saatmine
  - reaalajas uute logiridade saatmine
- paneb kirja lühikese töövoo, mida tunnis või esitluses näidata
- dokumenteerib, millised logifailid saadetakse ja mis on kasutatud `service` nimi

Valmis tulemus Husseinilt:

- olemas API võtmed ja kontrollitud ühendus
- testid on läbi tehtud
- demo jaoks on olemas lühike selgitus ja tegevusjärjekord

## Git branchide töökorraldus

Selle VR 10.5 ülesande jaoks kasutame kolme eraldi branchi, mis on loodud `main` branchi pealt:

- `vr105-maksim`
- `vr105-nikita`
- `vr105-hussein`

Töövoog on järgmine:

1. Kõik võtavad aluseks kõige värskema `main` branchi.
2. Seejärel liigub iga liige oma tööbranchile.
3. Iga liige teeb muudatused ainult oma branchil.
4. Kui töö on valmis, teeb iga liige commitid oma branchile.
5. Lõpus avab iga liige pull requesti oma branchilt tagasi `main` branchi.

Oluline märkus:

- meeskond ei tööta otse `main` branchil
- `main` on ühine baas ja koht, kuhu valmis töö PR-iga tagasi ühendatakse

Näidis käsud:

```bash
git switch main
git pull origin main

git switch vr105-maksim
# või
git switch vr105-nikita
# või
git switch vr105-hussein
```

## Kui tehakse ka MySQL logide osa

Kui meeskond jõuab lisada ka `logs/mysql.log` toe, siis töö jaguneb nii:

- Maksim ühendab MySQL logifaili shipperi sisendiks või Docker Compose teenuse kaudu kaasa
- Nikita lisab MySQL rea parsingu ja sobiva `metadata`
- Hussein testib, et MySQL logirida jõuab samuti API-sse

## Testimisplaan

### Ühenduse test

1. Kontrollida, et API vastab:
   - `GET /api/v1/ping`
2. Kontrollida autentimisega endpointi:
   - `GET /api/v1/health`

### "Viimased 5 rida" test

1. Käivitada skript valitud logifaili vastu.
2. Veenduda, et 5 rida loetakse failist sisse.
3. Veenduda, et iga rea kohta tehakse eraldi POST päring.
4. Kontrollida API vastusest, mitu kirjet indekseeriti.

### Reaalajas test

1. Käivitada tail-shipper.
2. Teha brauseris mitu päringut veebirakendusele.
3. Kontrollida, et uued read ilmuvad kohe logifaili.
4. Kontrollida, et skript saadab need kohe API-sse.

## Riskid ja vead

### 401 Unauthorized

- kontrollida `x-api-id` ja `x-api-key` väärtusi
- kontrollida päiste nimekuju ja kirjapilti

### 400 Bad Request

- kontrollida, et body on massiiv, mitte üksik objekt
- kontrollida, et kasutatakse välja `service`, mitte `project`
- kontrollida, et `metadata` oleks olemas
- kontrollida, et `level` oleks üks lubatud väärtustest:
  - `DEBUG`
  - `INFO`
  - `WARN`
  - `ERROR`
  - `CRITICAL`

### 500 või 503

- serveripoolne probleem või downstream Elasticsearch pole saadaval
- proovida uuesti ja logida vastuse sisu

### Vale endpoint

- `POST` tuleb saata aadressile `/api/v1/logs`
- ainult `/api/v1` aadressile saatmine ei ole piisav

## Lõppotsus

Meie projekti jaoks on kõige mõistlikum lahendus:

1. Teha kõigepealt väike tõestusskript, mis saadab `apache_access.log` faili viimased 5 rida LogForwardi API-sse.
2. Seejärel teha eraldi reaalajas jälgiv shipper-skript `tail -f` loogikaga.
3. Lõpuks käivitada see Docker Compose eraldi teenusena, et logide saatmine oleks automaatne.

See lähenemine kasutab ära meie olemasolevat failipõhist logiarhitektuuri ja nõuab minimaalseid muudatusi põhiveebirakendusse.
