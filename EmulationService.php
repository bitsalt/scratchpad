<?php


namespace App\Services;


class EmulationService
{
    private static $instance;


    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new EmulationService();
        }
    }


    public static function getEmployeeData($email)
    {
        if (config('app.env') == 'local') {
            return self::getTestData();
        }
        // try regular employees first
        $query = "SELECT ppf.person_id, ppf.employee_number, ppf.full_name, ppf.email_address,
                       ppf.effective_start_date, ppf.effective_end_date,ppf.person_type_id,
                       apps.wcs_uwcprep_pkg.Getalluserpersontypes(ppf.person_id, sysdate) AS person_type,
                       sup.organization, sup.empdff_supervisor_id, sup.asn_supervisor_id,
                       sup.org_code, sup.position_name
                    FROM apps.per_REDACTED_f ppf
                    JOIN apps.wcs_REDACTED_v sup on sup.person_id = ppf.person_id
                    WHERE ppf.email_address = :email
                    AND (apps.WCS_UWCPREP_PKG.getalluserpersontypes(ppf.person_id, sysdate) like 'Employee%'
                    OR apps.WCS_UWCPREP_PKG.getalluserpersontypes(ppf.person_id, sysdate) like 'Cont%')
                        AND Trunc(sysdate) BETWEEN ppf.effective_start_date
                    AND ppf.effective_end_date";

        $bindVars = [
            ':email' => $email,
        ];

        $results = self::fetchGenericResults($query, $bindVars);
        echo 'results: '; dd($results);
        if (count($results) == 1) {
            return $results[0];
        } elseif (count($results) == 0) {
            return self::employeeDataFailsafe($email);
        }
        return [];
    }

    /**
     * Not all employees are in per_all_people_f. Use this as a fall-back to catch any others.
     * @param $email
     * @return array|mixed
     */
    private static function employeeDataFailsafe($email)
    {
        $query = "select ppf.person_id, ppf.employee_number, ppf.full_name,
                        ppf.email_address, ppf.effective_start_date, ppf.effective_end_date,
                        ppf.person_type_id,
                        apps.WCS_UWCPREP_PKG.getalluserpersontypes(ppf.person_id, sysdate) AS person_type,
                        hou.name AS organization_name,
                        ppd.segment1 AS position_name
                    from apps.per_REDACTED_f ppf
                    inner join apps.PER_REDACTED_F paf on ppf.person_id = paf.person_id
                    inner join apps.WCS_REDACTED_V papf on papf.position_id = paf.position_id
                    inner join apps.PER_REDACTED_DEFINITIONS ppd on ppd.position_definition_id = papf.position_definition_id
                    inner join apps.HR_REDACTED_UNITS hou on hou.organization_id = paf.organization_id
                    where ppf.email_address = :email
                        and trunc(sysdate) BETWEEN ppf.effective_start_date AND ppf.effective_end_date
                        and trunc(sysdate) BETWEEN paf.effective_start_date AND paf.effective_end_date
                        and paf.primary_flag = 'Y' AND paf.assignment_status_type_id = 1";

        $bindVars = [
            ':email' => $email,
        ];

        $results = self::fetchGenericResults($query, $bindVars);
        if (count($results) == 1) {
            return $results[0];
        }
        return [];
    }


    public static function getContractorData($email)
    {
        $query = "select ctr.person_number, ctr.person_type, ctr.organization_name,
                        ctr.location, ctr.job, ctr.email_address, ctr.full_name,
                        ctr.supervisor_emailid as manager_email
                    from APPS.WCS_REDACTED_V ctr
                    where ctr.email_address = :email";

        $bindVars = [
            ':email' => $email,
        ];

        $results = self::fetchGenericResults($query, $bindVars);
        if (count($results) == 1) {
            return $results[0];
        }
        return [];
    }


    public static function getContractorManagerId($managerEmail)
    {
        $query = "select person_id
                    from apps.per_REDACTED_f
                    where email_address = :email
                    and trunc(sysdate) between effective_start_date and effective_end_date";

        $bindVars = [
            ':email' => $managerEmail,
        ];

        $results = self::fetchGenericResults($query, $bindVars);
        if ($results) {
            return $results[0]['person_id'];
        }
        return null;
    }


    public static function isValidEmployee($personId)
    {
        $query = "SELECT *
                    FROM APPS.WCS_REDACTED_V
                    WHERE person_id = :personId";
        $bindVars = [
            ':personId' => $personId,
        ];

        $result = self::fetchGenericResults($query, $bindVars);
        if (empty($result)) {
            return false;
        }
        return true;
    }


    protected static function fetchGenericResults($query, $bindVars=[])
    {
        self:: getInstance();

        $result = [];
        $counter = 0;
        $stmt = self::prepareStatement($query, $bindVars);
        while( $result_temp = oci_fetch_object($stmt) ) {
            foreach ($result_temp as $key => $value) {
                $result[$counter][strtolower($key)] = trim($value);
            }
            $counter++;
        }
        return $result;
    }


    protected static function prepareStatement($query, $bindVars=[])
    {
        if( function_exists('oci_connect') ) {
            $statement = oci_parse(self::$connection, $query);

            if (!empty($bindVars)) {
                foreach ($bindVars as $name => $value) {
                    oci_bind_by_name($statement, $name, $value);
                }
            }

            oci_execute($statement);
            return $statement;
        }
    }


}
