<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

<a name="unreleased"></a>
## [Unreleased]


<a name="1.1.0"></a>
## [1.1.0] - 2026-10-27
### ✨ Features
- add GitLab mirroring workflow ([610173c](https://github.com/GuansGuans/swiss-knife/commit/610173c))
- **NetEaseCloudMusic:** add fresh command for database management ([8fadef5](https://github.com/GuansGuans/swiss-knife/commit/8fadef5))

### 🐞 Bug Fixes
- **net-ease-cloud-music:** improve command clarity and timeout settings ([48104ad](https://github.com/GuansGuans/swiss-knife/commit/48104ad))

### 💅 Code Refactorings
- enhance process aware browser creation ([d8b3f18](https://github.com/GuansGuans/swiss-knife/commit/d8b3f18))
- **NetEaseCloudMusic:** add resource usage logging ([1aac8ca](https://github.com/GuansGuans/swiss-knife/commit/1aac8ca))
- **NetEaseCloudMusicCommand:** rename log model and update references ([097a1a5](https://github.com/GuansGuans/swiss-knife/commit/097a1a5))
- **NetEaseCloudMusicCommand:** refactor login process ([65e490b](https://github.com/GuansGuans/swiss-knife/commit/65e490b))
- **app:** add condition to booting process in non-production environments ([4bafb1d](https://github.com/GuansGuans/swiss-knife/commit/4bafb1d))
- **net-ease-cloud-music:** simplify cookie handling in login process ([9d272d1](https://github.com/GuansGuans/swiss-knife/commit/9d272d1))
- **net-ease-cloud-music:** increase timeout for installation step ([c5aecc3](https://github.com/GuansGuans/swiss-knife/commit/c5aecc3))

### 🏎 Performance Improvements
- increase timeout and add Chrome feature exclusions ([5e208d4](https://github.com/GuansGuans/swiss-knife/commit/5e208d4))
- enhance browser setup and configuration ([87dd826](https://github.com/GuansGuans/swiss-knife/commit/87dd826))
- **browser:** enhance browser configuration for improved performance ([5fe0ed6](https://github.com/GuansGuans/swiss-knife/commit/5fe0ed6))


<a name="1.0.0"></a>
## [1.0.0] - 2026-10-22

<a name="1.0.0-rc1"></a>
## [1.0.0-rc1] - 2026-10-19
### ✨ Features
- **command:** Add image optimization command ([7559176](https://github.com/GuansGuans/swiss-knife/commit/7559176))
- **command:** Add NetEaseCloudMusicCommand ([1cb6c6e](https://github.com/GuansGuans/swiss-knife/commit/1cb6c6e))

### 💅 Code Refactorings
- **command:** Simplify screenshot filename formatting and logging ([90bc594](https://github.com/GuansGuans/swiss-knife/commit/90bc594))


<a name="1.0.0-beta1"></a>
## 1.0.0-beta1 - 2026-10-16
### 💅 Code Refactorings
- **DatabaseServiceProvider:** Simplify production checks and enable foreign keys for SQLite ([0465830](https://github.com/GuansGuans/swiss-knife/commit/0465830))
- **app:** Simplify timezone handling and clean up tests ([22b4241](https://github.com/GuansGuans/swiss-knife/commit/22b4241))


[Unreleased]: https://github.com/GuansGuans/swiss-knife/compare/1.1.0...HEAD
[1.1.0]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0-rc1...1.0.0
[1.0.0-rc1]: https://github.com/GuansGuans/swiss-knife/compare/1.0.0-beta1...1.0.0-rc1
