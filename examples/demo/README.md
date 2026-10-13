# Live Activities simulator demo

Run from the repository root:

```sh
composer install
php bin/demo setup
```

Then start the mock server and a device in separate terminals:

```sh
php bin/demo mock     # loopback fixture server; keep running
php bin/demo ios      # build + launch; --udid=... selects an iPhone
php bin/demo android  # --serial=emulator-5554 selects a device
php bin/demo e2e      # once a device is running; --platform=android
```

The mock server returns a deterministic delivery scenario and needs no account.
Its address defaults to `http://127.0.0.1:8787`; change `MOCK_SERVER_URL` in
`.env` for another setup.

iOS needs Xcode, a simulator runtime and CocoaPods. Android needs the Android
Studio SDK, an ARM64 emulator, and a JDK 17 or 21 — set `DEMO_JAVA_HOME` if the
bundled Java is too new.
