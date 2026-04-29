use strict;
use warnings;

use Cwd qw(abs_path);
use File::Basename qw(dirname);
use FindBin qw($Bin);

my $command = shift @ARGV // '';
my $root = abs_path($Bin // '.');

sub usage {
    print <<'USAGE';
Usage:
  perl localcms.pl --build
  perl localcms.pl --help

Commands:
  --build   Export the public site into export/
  --help    Show this help message
USAGE
}

if ($command eq '--help' || $command eq '-h' || $command eq '') {
    usage();
    exit($command eq '' ? 1 : 0);
}

if ($command ne '--build') {
    die "Unknown command: $command\n";
}

my $bootstrap = "$root/bootstrap/app.php";
my $script = "$root/scripts/build-static-site.php";

die "Run this command from the Local CMS repo checkout.\n" if !-f $bootstrap || !-f $script;

chdir $root or die "Unable to change directory to $root: $!\n";

my @command = ('php', $script);
my $exit_code = system @command;

if ($exit_code == -1) {
    die "Failed to start php: $!\n";
}

exit($exit_code >> 8);