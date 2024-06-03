# coding: utf-8
lib = File.expand_path('../lib', __FILE__)
$LOAD_PATH.unshift(lib) unless $LOAD_PATH.include?(lib)
require 'with_env/version'

Gem::Specification.new do |spec|
  spec.name          = "with_env"
  spec.version       = WithEnv::VERSION
  spec.authors       = ["Zach Dennis"]
  spec.email         = ["zach.dennis@gmail.com"]

  spec.summary       = %q{WithEnv is an extremely small helper module for executing code with ENV variables.}
  spec.description   = %q{
    WithEnv is an extremely small helper module for executing code with ENV variables. It exists because
    I got tired of re-writing or copying over a #with_env helper method for the 131st time.
  }
  spec.homepage      = "https://github.com/mhs/with_env-rb"
  spec.license       = "MIT"

  spec.files         = `git ls-files -z`.split("\x0").reject { |f| f.match(%r{^(test|spec|features)/}) }
  spec.bindir        = "exe"
  spec.executables   = spec.files.grep(%r{^exe/}) { |f| File.basename(f) }
  spec.require_paths = ["lib"]

  spec.add_development_dependency "bundler", "~> 1.10"
  spec.add_development_dependency "rake", "~> 10.0"
  spec.add_development_dependency "rspec", "~> 3"
end
