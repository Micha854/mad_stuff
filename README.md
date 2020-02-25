# mad_stuff with PHP :)

#### HELP ON DISCORD: https://discord.gg/uYc22zn

### index.php
- shows all devices live data with colored highlights, sorting and notification function

![MAD-Devices](https://raw.githubusercontent.com/Micha854/mad_stuff/master/images/status_page.png)

- Roundtimes can be record for mon_mitm. this option is default disable, for activate set the option "record" value to "1" on config.json. The "index.php" requires write access !!!
![MAD-Devices](https://raw.githubusercontent.com/Micha854/mad_stuff/master/images/roundtimes.png)

### mad_set.php
- IV List Managing &amp; Route recalc

### mad_stats.php
- shows stats of online/offline on all devices with colored highlights

#### install

create a sql table for chart data and put the name of the database on config file under [py-option] section:

```
CREATE TABLE `status` (
  `createdate` date NOT NULL,
  `origin` varchar(50) NOT NULL,
  `status` int(1) DEFAULT NULL,
  `time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `status`
  ADD KEY `createdate` (`createdate`),
  ADD KEY `origin` (`origin`);
COMMIT;
```

the python script must always run for the worker statistics to work correctly

![MAD-Devices](https://raw.githubusercontent.com/Micha854/mad_stuff/master/images/stats_page.png)
