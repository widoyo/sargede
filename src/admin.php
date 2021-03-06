<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Tinggi Muka Air

$app->group('/admin', function() use ($loggedinMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');
        $sampling = $request->getParam('sampling', null);
        if (empty($sampling)) {
            $sampling = date('Y-m-d');
        }

        // $hari = $request->getParam('sampling', date('Y-m-d'));//"2019-06-26");
        // $prev_date = date('Y-m-d', strtotime($hari .' -1day'));
        // $next_date = date('Y-m-d', strtotime($hari .' +1day'));
        // $from = "{$hari} 00:00:00";
        // $to = "{$hari} 23:55:00";

        // ADMIN
        if ($user['role'] == 1)
        {
            $from = date('Y-m-d 00:00:00', strtotime($sampling));
            $to = date('Y-m-d 23:59:59', strtotime($sampling));
            $prev = date('Y-m-d', strtotime("{$sampling} -1day"));
            $next = date('Y-m-d', strtotime("{$sampling} +1day"));

            $lokasi_tma = $this->db->query("SELECT * FROM lokasi WHERE jenis='2' ORDER BY nama")->fetchAll();
            $tmas_temp = $this->db->query("SELECT
                                tma.*
                            FROM
                                tma
                            WHERE
                                tma.manual IS NOT NULL
                                AND sampling BETWEEN '{$from}' AND '{$to}'
                            ORDER BY sampling")->fetchAll();
            $tmas = [];
            foreach ($tmas_temp as $tma) {
                $date = date('Y-m-d', strtotime($tma['sampling']));
                $time = date('H:i', strtotime($tma['sampling']));
                $lokasi_id = $tma['lokasi_id'];

                // if (!isset($tmas[$date])) {
                //     $tmas[$date] = [];
                // }
                if (!isset($tmas[$lokasi_id])) {
                    $tmas[$lokasi_id] = [
                        'sampling' => $date,
                        'jam7' => "-",
                        'jam12' => "-",
                        'jam17' => "-",
                        // 'lokasi' => $tma['lokasi_nama']
                    ];
                }

                switch ($time) {
                    case '07:00':
                        $tmas[$lokasi_id]['jam7'] = $tma['manual'];
                        break;
                    case '12:00':
                        $tmas[$lokasi_id]['jam12'] = $tma['manual'];
                        break;
                    case '17:00':
                        $tmas[$lokasi_id]['jam17'] = $tma['manual'];
                        break;
                }
            }
            foreach ($lokasi_tma as &$l) {
                $l['manual'] = isset($tmas[$l['id']]) ? $tmas[$l['id']] : null;
            }
            unset($l);
            
            // CH
            $lokasi_ch = $this->db->query("SELECT * FROM lokasi WHERE jenis='1' ORDER BY nama")->fetchAll();
            foreach ($lokasi_ch as &$l) {
                $l['manual'] = $this->db->query("SELECT * FROM manual_daily
                    WHERE lokasi_id={$l['id']}
                        AND sampling BETWEEN '{$from}' AND '{$to}'
                    ORDER BY sampling DESC
                    LIMIT 1")
                    ->fetch();
            }
            unset($l);
            
            // KLIMAT
            $lokasi_klimat = $this->db->query("SELECT * FROM lokasi WHERE jenis='4' ORDER BY nama")->fetchAll();
            foreach ($lokasi_klimat as &$l) {
                $l['manual'] = $this->db->query("SELECT * FROM manual_daily
                    WHERE lokasi_id={$l['id']}
                        AND sampling BETWEEN '{$from}' AND '{$to}'
                    ORDER BY sampling DESC
                    LIMIT 1")
                    ->fetch();
            }
            unset($l);

            return $this->view->render($response, 'admin/index.html', [
                'lokasi_tma' => $lokasi_tma,
                'lokasi_ch' => $lokasi_ch,
                'lokasi_klimat' => $lokasi_klimat,
                'prev' => $prev,
                'next' => $next,
                'sampling' => $sampling,
            ]);
        }
        else
        {
            $from = date('Y-m-01 00:00:00', strtotime($sampling));
            $to = date('Y-m-t 23:59:59', strtotime($sampling));
            $prev = date('Y-m-d', strtotime("{$sampling} first day of last month"));
            $next = date('Y-m-d', strtotime("{$sampling} first day of next month"));

            // PENGAMAT
            $lokasi = $this->db->query("SELECT * FROM lokasi WHERE id={$user['lokasi_id']}")->fetch();
            if ($lokasi['jenis'] == 2) // tma
            {
                $tmas_temp = $this->db->query("SELECT * FROM tma
                    WHERE lokasi_id={$user['lokasi_id']}
                        AND sampling BETWEEN '{$from}' AND '{$to}'
                    ORDER BY sampling")->fetchAll();

                $tmas = [];
                foreach ($tmas_temp as $tma) {
                    $date = date('Y-m-d', strtotime($tma['sampling']));
                    $time = date('H:i', strtotime($tma['sampling']));

                    if (!isset($tmas[$date])) {
                        $tmas[$date] = [
                            'sampling' => $date,
                            'jam7' => null,
                            'jam12' => null,
                            'jam17' => null,
                        ];
                    }

                    switch ($time) {
                        case '07:00':
                            $tmas[$date]['jam7'] = $tma['manual'];
                            break;
                        case '12:00':
                            $tmas[$date]['jam12'] = $tma['manual'];
                            break;
                        case '17:00':
                            $tmas[$date]['jam17'] = $tma['manual'];
                            break;
                    }
                }

                $current_hour = date('H');
                if ($current_hour < 12) {
                    $inputjam = '07:00';
                } else if ($current_hour < 17) {
                    $inputjam = '12:00';
                } else {
                    $inputjam = '17:00';
                }

                return $this->view->render($response, 'admin/tma.html', [
                    'lokasi' => $lokasi,
                    'tmas' => $tmas,
                    'inputjam' => $inputjam,
                    'prev' => $prev,
                    'next' => $next,
                    'sampling' => $sampling,
                ]);
            }
            else // ch & klimat
            {
                $klimat = $this->db->query("SELECT * FROM manual_daily
                    WHERE lokasi_id={$user['lokasi_id']}
                        AND sampling BETWEEN '{$from}' AND '{$to}'
                    ORDER BY sampling"
                    )->fetchAll();

                return $this->view->render($response, 'admin/klimat.html', [
                    'lokasi' => $lokasi,
                    'klimat' => $klimat,
                    'prev' => $prev,
                    'next' => $next,
                    'sampling' => $sampling,
                ]);
            }
        }
    })->setName('admin');

    $this->get('/periodik', function(Request $request, Response $response, $args) {
        $now = date("Y-m-d H:i");
        $periodics = $this->db->query("SELECT periodik.*, lokasi.nama as lok_nama
                                        FROM periodik
                                            LEFT JOIN lokasi ON (lokasi.id = periodik.lokasi_id)
                                        WHERE periodik.sampling <= '{$now}'
                                        ORDER BY periodik.sampling DESC
                                        LIMIT 300")->fetchAll();

        return $this->view->render($response, 'admin/periodik.html', [
            'periodics' => $periodics
        ]);
    })->setName('admin.periodik');

    /**
     * GROUP ADD
     */
    $this->group('/add', function() {

        /**
         * ADD TMA
         */
        $this->post('/tma', function(Request $request, Response $response) {
            $user = $request->getAttribute('user'); // didapat dari middleware
            $lokasi = $request->getAttribute('lokasi'); // didapat dari middleware
            $now = date('Y-m-d H:i:s');

            $form = $request->getParams();
            // check if exists, if not insert if yes update
            $sampling = $form['sampling'] ." {$form['jam']}";
            $available = $this->db->query("SELECT * FROM tma WHERE lokasi_id={$lokasi['id']} AND sampling='{$sampling}'")->fetch();
            if (!empty($available)) {
                $stmt = $this->db->prepare("UPDATE tma SET
                                    manual=:manual,
                                    received=:received,
                                    petugas=:petugas
                                 WHERE lokasi_id=:lokasi_id AND sampling=:sampling");
                $stmt->execute([
                    ':sampling' => $form['sampling'] ." {$form['jam']}",
                    ':received' => $now,
                    ':petugas' => $user['id'],
                    ':manual' => $form['manual'],
                    ':lokasi_id' => $lokasi['id'],
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO tma (
                                    sampling,
                                    manual,
                                    lokasi_id,
                                    received,
                                    petugas
                                ) VALUES (
                                    :sampling,
                                    :manual,
                                    :lokasi_id,
                                    :received,
                                    :petugas
                                )");
                $stmt->execute([
                    ':sampling' => $form['sampling'] ." {$form['jam']}",
                    ':lokasi_id' => $lokasi['id'],
                    ':received' => $now,
                    ':petugas' => $user['id'],
                    ':manual' => $form['manual'],
                ]);
            }

            $sampling = date('Y-m-d', strtotime($sampling));
            return $response->withRedirect("/admin?sampling={$sampling}");
        })->setName('admin.add.tma');

        $this->post('/curahhujan', function(Request $request, Response $response) {
            $user = $request->getAttribute('user'); // didapat dari middleware
            $lokasi = $request->getAttribute('lokasi'); // didapat dari middleware
            $now = date('Y-m-d H:i:s');

            $form = $request->getParams();
            // check if exists, if not insert if yes update
            $sampling = $form['sampling'] ." 07:00:00";
            $available = $this->db->query("SELECT * FROM manual_daily WHERE lokasi_id={$lokasi['id']} AND sampling='{$sampling}'")->fetch();
            if (!empty($available)) {
                $stmt = $this->db->prepare("UPDATE manual_daily SET
                                    rain=:rain,
                                    received=:received,
                                    petugas=:petugas
                                 WHERE lokasi_id=:lokasi_id AND sampling=:sampling");
                $stmt->execute([
                    ':sampling' => $form['sampling'] ." 07:00:00",
                    ':received' => $now,
                    ':petugas' => $user['username'],
                    ':rain' => $form['rain'],
                    ':lokasi_id' => $lokasi['id']
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO manual_daily (
                                    sampling,
                                    rain,
                                    lokasi_id,
                                    received,
                                    petugas
                                ) VALUES (
                                    :sampling,
                                    :rain,
                                    :lokasi_id,
                                    :received,
                                    :petugas
                                )");
                $stmt->execute([
                    ':sampling' => $form['sampling'] ." 07:00:00",
                    ':lokasi_id' => $lokasi['id'],
                    ':received' => $now,
                    ':petugas' => $user['username'],
                    ':rain' => $form['rain'],
                ]);
            }

            $sampling = date('Y-m-d', strtotime($sampling));
            return $response->withRedirect("/admin?sampling={$sampling}");
        })->setName('admin.add.curahhujan');

        $this->post('/klimat', function(Request $request, Response $response) {
            $user = $request->getAttribute('user'); // didapat dari middleware
            $lokasi = $request->getAttribute('lokasi'); // didapat dari middleware
            $now = date('Y-m-d H:i:s');

            $form = $request->getParams();
            // check if exists, if not insert if yes update
            $sampling = $form['sampling'] ." 07:00:00";
            $available = $this->db->query("SELECT * FROM manual_daily WHERE lokasi_id={$lokasi['id']} AND sampling='{$sampling}'")->fetch();
            if (!empty($available)) {
                $stmt = $this->db->prepare("UPDATE manual_daily SET
                                    petugas=:petugas,
                                    received=:received,
                                    temp_max=:temp_max,
                                    temp_min=:temp_min,
                                    temp_avg=:temp_avg,
                                    humi=:humi,
                                    temp_tangki=:temp_tangki,
                                    evaporation=:evaporation,
                                    wind=:wind,
                                    rad=:rad,
                                    rain=:rain
                                 WHERE lokasi_id={$lokasi['id']} AND sampling=:sampling");
                $stmt->execute([
                    ':sampling' => $form['sampling'] ." 07:00:00",
                    ':petugas' => $user['username'],
                    ':received' => $now,
                    ':temp_max' => $form['temp_max'] ?: null,
                    ':temp_min' => $form['temp_min'] ?: null,
                    ':temp_avg' => $form['temp_avg'] ?: null,
                    ':humi' => $form['humi'] ?: null,
                    ':temp_tangki' => $form['temp_tangki'] ?: null,
                    ':evaporation' => $form['evaporation'] ?: null,
                    ':wind' => $form['wind'] ?: null,
                    ':rad' => $form['rad'] ?: null,
                    ':rain' => $form['rain'] ?: null,
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO manual_daily (
                                    lokasi_id,
                                    sampling,
                                    petugas,
                                    received,
                                    temp_max,
                                    temp_min,
                                    temp_avg,
                                    humi,
                                    temp_tangki,
                                    evaporation,
                                    wind,
                                    rad,
                                    rain
                                ) VALUES (
                                    :lokasi_id,
                                    :sampling,
                                    :petugas,
                                    :received,
                                    :temp_max,
                                    :temp_min,
                                    :temp_avg,
                                    :humi,
                                    :temp_tangki,
                                    :evaporation,
                                    :wind,
                                    :rad,
                                    :rain
                                )");
                $stmt->execute([
                    ':lokasi_id' => $lokasi['id'],
                    ':sampling' => $form['sampling'] ." 07:00:00",
                    ':petugas' => $user['username'],
                    ':received' => $now,
                    ':temp_max' => $form['temp_max'] ?: null,
                    ':temp_min' => $form['temp_min'] ?: null,
                    ':temp_avg' => $form['temp_avg'] ?: null,
                    ':humi' => $form['humi'] ?: null,
                    ':temp_tangki' => $form['temp_tangki'] ?: null,
                    ':evaporation' => $form['evaporation'] ?: null,
                    ':wind' => $form['wind'] ?: null,
                    ':rad' => $form['rad'] ?: null,
                    ':rain' => $form['rain'] ?: null,
                ]);
            }

            $sampling = date('Y-m-d', strtotime($sampling));
            return $response->withRedirect("/admin?sampling={$sampling}");
        })->setName('admin.add.klimat');

    })->add(function(Request $request, Response $response, $next) {

        // hanya user role pengamat & admin yg dapat akses
        $user = $request->getAttribute('user');
        if ($user['role'] != 2) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        return $next($request, $response);
    });

    /**
     * GROUP EDIT
     */
    $this->group('/edit', function() {
        $this->post('/tma', function(Request $request, Response $response) {
            $user = $request->getAttribute('user');
            $form = $request->getParams();
            $lokasi_id = $form['lokasi_id'];
            foreach ($form['tma'] as $dt) {
                $sampling = $dt['sampling'];
                $tma = $this->db->query("SELECT * FROM tma
                    WHERE lokasi_id={$lokasi_id}
                        AND sampling='{$sampling}'")->fetch();
                if (!$tma) {
                    throw new Slim\Exception\NotFoundException($request, $response);
                }
                
                $stmt = $this->db->prepare("UPDATE tma
                    SET
                        manual=:manual
                    WHERE lokasi_id=:lokasi_id
                        AND sampling=:sampling");
                $stmt->execute([
                    'lokasi_id' => $lokasi_id,
                    'sampling' => "{$sampling}",
                    'manual' => $dt['manual'],
                ]);
            }
            $sampling = date('Y-m-d', strtotime($sampling));
            return $response->withRedirect("/admin?sampling={$sampling}");
        })->setName('admin.edit.tma');

        $this->post('/curahhujan', function(Request $request, Response $response) {
            $user = $request->getAttribute('user');
            $form = $request->getParams();
            $sampling = $form['sampling'] ." 07:00:00";
            $manual = $this->db->query("SELECT * FROM manual_daily WHERE lokasi_id={$form['lokasi_id']} AND sampling='{$sampling}'")->fetch();
            if (!$manual) {
                throw new Slim\Exception\NotFoundException($request, $response);
            }
            
            $stmt = $this->db->prepare("UPDATE manual_daily 
                SET rain=:rain
                WHERE lokasi_id=:lokasi_id AND sampling=:sampling");
            $stmt->execute([
                'lokasi_id' => $form['lokasi_id'],
                'sampling' => $sampling,
                'rain' => $form['rain'],
            ]);

            return $response->withRedirect("/admin?sampling={$form['sampling']}");
        })->setName('admin.edit.curahhujan');

        $this->post('/klimat', function(Request $request, Response $response) {
            $user = $request->getAttribute('user'); // didapat dari middleware
            $lokasi = $request->getAttribute('lokasi'); // didapat dari middleware
            $now = date('Y-m-d H:i:s');

            $form = $request->getParams();
            // check if exists, if not insert if yes update
            $sampling = $form['sampling'] ." 07:00:00";
            $available = $this->db->query("SELECT * FROM manual_daily WHERE lokasi_id={$lokasi['id']} AND sampling='{$sampling}'")->fetch();
            if (!$available) {
                throw new Slim\Exception\NotFoundException($request, $response);
            }

            $stmt = $this->db->prepare("UPDATE manual_daily SET
                                temp_max=:temp_max,
                                temp_min=:temp_min,
                                temp_avg=:temp_avg,
                                humi=:humi,
                                temp_tangki=:temp_tangki,
                                evaporation=:evaporation,
                                wind=:wind,
                                rad=:rad,
                                rain=:rain
                                WHERE lokasi_id={$lokasi['id']} AND sampling=:sampling");
            $stmt->execute([
                ':sampling' => $form['sampling'] ." 07:00:00",
                ':temp_max' => $form['temp_max'] ?: null,
                ':temp_min' => $form['temp_min'] ?: null,
                ':temp_avg' => $form['temp_avg'] ?: null,
                ':humi' => $form['humi'] ?: null,
                ':temp_tangki' => $form['temp_tangki'] ?: null,
                ':evaporation' => $form['evaporation'] ?: null,
                ':wind' => $form['wind'] ?: null,
                ':rad' => $form['rad'] ?: null,
                ':rain' => $form['rain'] ?: null,
            ]);

            $sampling = date('Y-m-d', strtotime($sampling));
            return $response->withRedirect("/admin?sampling={$sampling}");
        })->setName('admin.edit.klimat');
    })->add(function(Request $request, Response $response, $next) {
        $user = $request->getAttribute('user', null);
        if ($user['role'] != 1) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $lokasi_id = $request->getParam('lokasi_id');
        $lokasi = $this->db->query("SELECT * FROM lokasi WHERE id={$lokasi_id}")->fetch();
        $request = $request->withAttribute('lokasi', $lokasi);

        return $next($request, $response);
    });

    /**
     * GROUP DELETE
     */
    $this->group('/delete', function() {
        $this->get('/tma', function(Request $request, Response $response) {
            $user = $request->getAttribute('user');
            $form = $request->getParams();
            $lokasi_id = $form['lokasi_id'];
            $sampling = $form['sampling'];
            $sampling = [
                "'{$sampling} 07:00'",
                "'{$sampling} 12:00'",
                "'{$sampling} 17:00'"
            ];
            $sampling = implode(",", $sampling);

            $stmt = $this->db->prepare("DELETE FROM tma WHERE lokasi_id=:lokasi_id AND sampling IN ({$sampling})");
            $stmt->execute([
                'lokasi_id' => $lokasi_id
            ]);
            
            return $response->withRedirect("/admin?sampling={$form['sampling']}");
        });

        $this->get('/curahhujan', function(Request $request, Response $response) {
            $user = $request->getAttribute('user');
            $form = $request->getParams();
            $lokasi_id = $form['lokasi_id'];
            $sampling = $form['sampling'] ." 07:00:00";
            
            $stmt = $this->db->prepare("DELETE FROM manual_daily
                WHERE lokasi_id=:lokasi_id AND sampling=:sampling");
            $stmt->execute([
                'lokasi_id' => $form['lokasi_id'],
                'sampling' => $sampling
            ]);

            return $response->withRedirect("/admin?sampling={$form['sampling']}");
        });

        $this->get('/klimat', function(Request $request, Response $response) {
            $user = $request->getAttribute('user');
            $form = $request->getParams();
            $lokasi_id = $form['lokasi_id'];
            $sampling = $form['sampling'] ." 07:00:00";
            
            $stmt = $this->db->prepare("DELETE FROM manual_daily
                WHERE lokasi_id=:lokasi_id AND sampling=:sampling");
            $stmt->execute([
                'lokasi_id' => $form['lokasi_id'],
                'sampling' => $sampling
            ]);

            return $response->withRedirect("/admin?sampling={$form['sampling']}");
        });
    })->add(function(Request $request, Response $response, $next) {
        $user = $request->getAttribute('user', null);
        if ($user['role'] != 1) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        $lokasi_id = $request->getParam('lokasi_id');
        $lokasi = $this->db->query("SELECT * FROM lokasi WHERE id={$lokasi_id}")->fetch();
        $request = $request->withAttribute('lokasi', $lokasi);

        return $next($request, $response);
    });
})->add(function(Request $request, Response $response, $next) {

    $user = $request->getAttribute('user', null);
    if ($user['role'] == 2) {
        $lokasi = $this->db->query("SELECT * FROM lokasi WHERE id={$user['lokasi_id']}")->fetch();
        $request = $request->withAttribute('lokasi', $lokasi);
    }

    return $next($request, $response);
})->add($loggedinMiddleware);
