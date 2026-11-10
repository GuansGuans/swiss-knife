<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

<a name="unreleased"></a>
## [Unreleased]


<a name="1.2.0"></a>
## [1.2.0] - 2026-11-10
### ✨ Features
- **ClearLogsCommand:** add command to clear old log files ([995bf4a5](https://github.com/GuansGuans/swiss-knife/commit/995bf4a5))

### 🐞 Bug Fixes
- **AppServiceProvider:** improve type hinting for environment variable mapping ([95b124cb](https://github.com/GuansGuans/swiss-knife/commit/95b124cb))
- **AppServiceProvider:** enhance environment variable parsing ([25c02379](https://github.com/GuansGuans/swiss-knife/commit/25c02379))
- **app.php:** replace ClearLogsCommand with command string ([5cf30fee](https://github.com/GuansGuans/swiss-knife/commit/5cf30fee))

### 💅 Code Refactorings
- simplify command normalization in ContextSubscriber ([9f946cc7](https://github.com/GuansGuans/swiss-knife/commit/9f946cc7))
- **AppServiceProvider:** Update Xdebug configuration handling ([f821e079](https://github.com/GuansGuans/swiss-knife/commit/f821e079))
- **AppServiceProvider:** Refactor configuration handling logic ([85c11782](https://github.com/GuansGuans/swiss-knife/commit/85c11782))
- **NetEaseCloudMusic:** update screenshot file path format ([8d78c7ec](https://github.com/GuansGuans/swiss-knife/commit/8d78c7ec))
- **NetEaseCloudMusicSnapshotCommand:** Update path to content configuration ([ee3d2fb9](https://github.com/GuansGuans/swiss-knife/commit/ee3d2fb9))
- **NetEaseCloudMusicUserHomeSnapshot:** Implement Orbit interface and update dependencies ([1f1e4953](https://github.com/GuansGuans/swiss-knife/commit/1f1e4953))
- **RulesTest:** improve callback string formatting ([cafd66ff](https://github.com/GuansGuans/swiss-knife/commit/cafd66ff))
- **console:** Improve path handling logic ([946612b9](https://github.com/GuansGuans/swiss-knife/commit/946612b9))

### ✅ Tests
- **Rules:** add validation tests for custom rules ([0e40b6fe](https://github.com/GuansGuans/swiss-knife/commit/0e40b6fe))

### 🤖 Continuous Integrations
- comment out Gitee mirroring configuration ([b1088efa](https://github.com/GuansGuans/swiss-knife/commit/b1088efa))
- **workflows:** add Gitee mirror action ([3ab16000](https://github.com/GuansGuans/swiss-knife/commit/3ab16000))
- **workflows:** add dependency for mirror workflow ([97fc6ba1](https://github.com/GuansGuans/swiss-knife/commit/97fc6ba1))
- **workflows:** update mirror and net-ease-cloud-music configurations ([1c171deb](https://github.com/GuansGuans/swiss-knife/commit/1c171deb))


<a name="1.1.0"></a>
## [1.1.0] - 2026-10-27
### ✨ Features
- add GitLab mirroring workflow ([610173cb](https://github.com/GuansGuans/swiss-knife/commit/610173cb))
- **NetEaseCloudMusic:** add fresh command for database management ([8fadef55](https://github.com/GuansGuans/swiss-knife/commit/8fadef55))

### 🐞 Bug Fixes
- **net-ease-cloud-music:** improve command clarity and timeout settings ([48104add](https://github.com/GuansGuans/swiss-knife/commit/48104add))

### 💅 Code Refactorings
- enhance process aware browser creation ([d8b3f181](https://github.com/GuansGuans/swiss-knife/commit/d8b3f181))
- **NetEaseCloudMusic:** add resource usage logging ([1aac8ca5](https://github.com/GuansGuans/swiss-knife/commit/1aac8ca5))
- **NetEaseCloudMusicCommand:** rename log model and update references ([097a1a5b](https://github.com/GuansGuans/swiss-knife/commit/097a1a5b))
- **NetEaseCloudMusicCommand:** refactor login process ([65e490b8](https://github.com/GuansGuans/swiss-knife/commit/65e490b8))
- **app:** add condition to booting process in non-production environments ([4bafb1d4](https://github.com/GuansGuans/swiss-knife/commit/4bafb1d4))
- **net-ease-cloud-music:** simplify cookie handling in login process ([9d272d15](https://github.com/GuansGuans/swiss-knife/commit/9d272d15))
- **net-ease-cloud-music:** increase timeout for installation step ([c5aecc31](https://github.com/GuansGuans/swiss-knife/commit/c5aecc31))

### 🏎 Performance Improvements
- increase timeout and add Chrome feature exclusions ([5e208d46](https://github.com/GuansGuans/swiss-knife/commit/5e208d46))
- enhance browser setup and configuration ([87dd826f](https://github.com/GuansGuans/swiss-knife/commit/87dd826f))
- **browser:** enhance browser configuration for improved performance ([5fe0ed6f](https://github.com/GuansGuans/swiss-knife/commit/5fe0ed6f))


<a name="1.0.0"></a>
## [1.0.0] - 2026-10-22

<a name="1.0.0-rc1"></a>
## [1.0.0-rc1] - 2026-10-19
### ✨ Features
- **command:** Add image optimization command ([7559176a](https://github.com/GuansGuans/swiss-knife/commit/7559176a))
- **command:** Add NetEaseCloudMusicCommand ([1cb6c6ef](https://github.com/GuansGuans/swiss-knife/commit/1cb6c6ef))

### 💅 Code Refactorings
- **command:** Simplify screenshot filename formatting and logging ([90bc5949](https://github.com/GuansGuans/swiss-knife/commit/90bc5949))


<a name="1.0.0-beta1"></a>
## 1.0.0-beta1 - 2026-10-16
### 💅 Code Refactorings
- **DatabaseServiceProvider:** Simplify production checks and enable foreign keys for SQLite ([0465830a](https://github.com/GuansGuans/swiss-knife/commit/0465830a))
- **app:** Simplify timezone handling and clean up tests ([22b4241c](https://github.com/GuansGuans/swiss-knife/commit/22b4241c))


[Unreleased]: https://github.com/GuansGuans/swiss-knife/compare/1.2.0...HEAD
[1.2.0]: https://github.com/GuansGuans/swiss-knife/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0-rc1...1.0.0
[1.0.0-rc1]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0-beta1...1.0.0-rc1
