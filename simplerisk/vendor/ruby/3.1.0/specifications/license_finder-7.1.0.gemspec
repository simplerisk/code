# -*- encoding: utf-8 -*-
# stub: license_finder 7.1.0 ruby lib

Gem::Specification.new do |s|
  s.name = "license_finder".freeze
  s.version = "7.1.0".freeze

  s.required_rubygems_version = Gem::Requirement.new(">= 0".freeze) if s.respond_to? :required_rubygems_version=
  s.require_paths = ["lib".freeze]
  s.authors = ["Ryan Collins".freeze, "Daniil Kouznetsov".freeze, "Andy Shen".freeze, "Shane Lattanzio".freeze, "Li Sheng Tai".freeze, "Vlad vassilovski".freeze, "Jacob Maine".freeze, "Matthew Kane Parker".freeze, "Ian Lesperance".freeze, "David Edwards".freeze, "Paul Meskers".freeze, "Brent Wheeldon".freeze, "Trevor John".freeze, "David Tengdin".freeze, "William Ramsey".freeze, "David Dening".freeze, "Geoff Pleiss".freeze, "Mike Chinigo".freeze, "Mike Dalessio".freeze, "Jeff Jun".freeze]
  s.date = "2022-11-28"
  s.description = "    LicenseFinder works with your package managers to find\n    dependencies, detect the licenses of the packages in them, compare\n    those licenses against a user-defined list of permitted licenses,\n    and give you an actionable exception report.\n".freeze
  s.email = ["labs-commoncode@pivotal.io".freeze]
  s.executables = ["license_finder".freeze, "license_finder_pip.py".freeze]
  s.files = ["bin/license_finder".freeze, "bin/license_finder_pip.py".freeze]
  s.homepage = "https://github.com/pivotal/LicenseFinder".freeze
  s.licenses = ["MIT".freeze]
  s.required_ruby_version = Gem::Requirement.new(">= 2.4.0".freeze)
  s.rubygems_version = "3.4.22".freeze
  s.summary = "Audit the OSS licenses of your application's dependencies.".freeze

  s.installed_by_version = "3.4.22".freeze if s.respond_to? :installed_by_version

  s.specification_version = 4

  s.add_runtime_dependency(%q<bundler>.freeze, [">= 0".freeze])
  s.add_runtime_dependency(%q<rubyzip>.freeze, [">= 1".freeze, "< 3".freeze])
  s.add_runtime_dependency(%q<thor>.freeze, ["~> 1.2".freeze])
  s.add_runtime_dependency(%q<tomlrb>.freeze, [">= 1.3".freeze, "< 2.1".freeze])
  s.add_runtime_dependency(%q<with_env>.freeze, ["= 1.1.0".freeze])
  s.add_runtime_dependency(%q<xml-simple>.freeze, ["~> 1.1.9".freeze])
  s.add_development_dependency(%q<addressable>.freeze, ["= 2.8.1".freeze])
  s.add_development_dependency(%q<capybara>.freeze, ["~> 3.32.2".freeze])
  s.add_development_dependency(%q<e2mmap>.freeze, ["~> 0.1.0".freeze])
  s.add_development_dependency(%q<fakefs>.freeze, ["~> 1.8.0".freeze])
  s.add_development_dependency(%q<matrix>.freeze, ["~> 0.1.0".freeze])
  s.add_development_dependency(%q<mime-types>.freeze, ["= 3.4.1".freeze])
  s.add_development_dependency(%q<pry>.freeze, ["~> 0.14.1".freeze])
  s.add_development_dependency(%q<rake>.freeze, ["~> 13.0.6".freeze])
  s.add_development_dependency(%q<rspec>.freeze, ["~> 3".freeze])
  s.add_development_dependency(%q<rspec-its>.freeze, ["~> 1.3.0".freeze])
  s.add_development_dependency(%q<rubocop>.freeze, ["~> 1.12.1".freeze])
  s.add_development_dependency(%q<rubocop-performance>.freeze, ["~> 1.10.2".freeze])
  s.add_development_dependency(%q<webmock>.freeze, ["~> 3.14".freeze])
  s.add_development_dependency(%q<nokogiri>.freeze, ["~> 1.10".freeze])
  s.add_development_dependency(%q<rack>.freeze, ["~> 3.0.0".freeze])
  s.add_development_dependency(%q<rack-test>.freeze, ["> 0.7".freeze, "~> 2.0.2".freeze])
end
