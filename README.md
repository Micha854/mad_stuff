# mad_stuff with PHP :)

### index.php
- shows all devices with colored highlights, sorting and notification function

![MAD-Devices](https://raw.githubusercontent.com/Micha854/mad_stuff/master/20200111_164238.jpg)

### mad_set.php
- IV List Managing &amp; Route recalc

### mad_stats.php
- shows stats of online/offline on all devices with colored highlights

#### install

create a sql table for chart data and put the name of the database on config file under [python] section:

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

![MAD-Devices](https://raw.githubusercontent.com/Micha854/mad_stuff/master/chart.png)
