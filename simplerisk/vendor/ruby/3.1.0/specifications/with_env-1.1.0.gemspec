# -*- encoding: utf-8 -*-
# stub: with_env 1.1.0 ruby lib

Gem::Specification.new do |s|
  s.name = "with_env".freeze
  s.version = "1.1.0".freeze

  s.required_rubygems_version = Gem::Requirement.new(">= 0".freeze) if s.respond_to? :required_rubygems_version=
  s.require_paths = ["lib".freeze]
  s.authors = ["Zach Dennis".freeze]
  s.bindir = "exe".freeze
  s.date = "2015-07-15"
  s.description = "\n    WithEnv is an extremely small helper module for executing code with ENV variables. It exists because\n    I got tired of re-writing or copying over a #with_env helper method for the 131st time.\n  ".freeze
  s.email = ["zach.dennis@gmail.com".freeze]
  s.homepage = "https://github.com/mhs/with_env-rb".freeze
  s.licenses = ["MIT".freeze]
  s.rubygems_version = "3.4.22".freeze
  s.summary = "WithEnv is an extremely small helper module for executing code with ENV variables.".freeze

  s.installed_by_version = "3.4.22".freeze if s.respond_to? :installed_by_version

  s.specification_version = 4

  s.add_development_dependency(%q<bundler>.freeze, ["~> 1.10".freeze])
  s.add_development_dependency(%q<rake>.freeze, ["~> 10.0".freeze])
  s.add_development_dependency(%q<rspec>.freeze, ["~> 3".freeze])
end
